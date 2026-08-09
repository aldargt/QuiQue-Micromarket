<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Role $administratorRole;

    private Role $cashierRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create(['code' => 'PRINCIPAL']);
        $this->administratorRole = Role::factory()->create([
            'name' => 'Administrador',
            'slug' => RoleSlug::Administrator->value,
        ]);
        $this->cashierRole = Role::factory()->create([
            'name' => 'Cajero',
            'slug' => RoleSlug::Cashier->value,
        ]);
    }

    public function test_administrator_and_cashier_can_list_and_search_categories(): void
    {
        Category::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Bebidas']);
        Category::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Panadería']);

        foreach ([$this->administrator(), $this->cashier()] as $user) {
            $this->actingAs($user)
                ->get(route('categories.index', ['search' => 'Bebi']))
                ->assertOk()
                ->assertSee('Bebidas')
                ->assertDontSee('Panadería');
        }
    }

    public function test_administrator_can_create_category_and_cashier_cannot(): void
    {
        $administrator = $this->administrator();
        $this->actingAs($administrator)->post(route('categories.store'), ['name' => '  Leches  '])
            ->assertRedirect(route('categories.index'));
        $this->assertDatabaseHas('categories', ['name' => 'Leches', 'created_by' => $administrator->id]);

        $this->actingAs($this->cashier())->get(route('categories.create'))->assertForbidden();
        $this->actingAs($this->cashier())->post(route('categories.store'), ['name' => 'Verduras'])->assertForbidden();
        $this->assertDatabaseMissing('categories', ['name' => 'Verduras']);
    }

    public function test_administrator_can_edit_category_and_returns_to_list(): void
    {
        $category = Category::factory()->create(['branch_id' => $this->branch->id]);
        $this->actingAs($this->administrator())->put(route('categories.update', $category), ['name' => 'Carnes'])
            ->assertRedirect(route('categories.index'))->assertSessionHas('status');
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Carnes']);
    }

    public function test_cashier_cannot_edit_or_toggle_categories_by_direct_url(): void
    {
        $category = Category::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $cashier = $this->cashier();

        $this->actingAs($cashier)->get(route('categories.edit', $category))->assertForbidden();
        $this->actingAs($cashier)->put(route('categories.update', $category), ['name' => 'Manipulada'])->assertForbidden();
        $this->actingAs($cashier)->patch(route('categories.toggle', $category))->assertForbidden();
        $this->assertTrue($category->fresh()->is_active);
    }

    public function test_administrator_can_deactivate_and_reactivate_categories(): void
    {
        $category = Category::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $user = $this->administrator();

        $this->actingAs($user)->patch(route('categories.toggle', $category))->assertRedirect(route('categories.index'));
        $this->assertFalse($category->fresh()->is_active);
        $this->actingAs($user)->patch(route('categories.toggle', $category))->assertRedirect(route('categories.index'));
        $this->assertTrue($category->fresh()->is_active);
    }

    public function test_cashier_category_list_hides_mutation_controls(): void
    {
        $category = Category::factory()->create(['branch_id' => $this->branch->id]);

        $this->actingAs($this->cashier())->get(route('categories.index'))
            ->assertOk()
            ->assertSee($category->name)
            ->assertDontSee('Crear categoría')
            ->assertDontSee('Editar')
            ->assertDontSee('Desactivar');
    }

    public function test_duplicate_names_are_rejected_after_whitespace_and_case_normalization(): void
    {
        Category::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Bebidas frías']);

        $this->actingAs($this->administrator())
            ->post(route('categories.store'), ['name' => '  bebidas   frías  '])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Category::query()->where('branch_id', $this->branch->id)->count());
    }

    public function test_category_names_only_need_to_be_unique_within_their_branch(): void
    {
        Category::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Bebidas']);
        $otherBranch = Branch::factory()->create();
        $administrator = User::factory()->create([
            'role_id' => $this->administratorRole->id,
            'branch_id' => $otherBranch->id,
        ]);

        $this->actingAs($administrator)->post(route('categories.store'), ['name' => 'Bebidas'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('categories', 2);
    }

    public function test_guest_inactive_and_unsupported_role_cannot_access_categories(): void
    {
        $this->get(route('categories.index'))->assertRedirect(route('login'));

        $inactive = $this->cashier(['is_active' => false]);
        $this->actingAs($inactive)->get(route('categories.index'))->assertRedirect(route('login'));

        $unsupportedRole = Role::factory()->create(['slug' => 'unsupported']);
        $unsupportedUser = User::factory()->create([
            'role_id' => $unsupportedRole->id,
            'branch_id' => $this->branch->id,
        ]);
        $this->actingAs($unsupportedUser)->get(route('categories.index'))->assertForbidden();
    }

    public function test_users_cannot_modify_categories_from_another_branch(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->administrator())
            ->put(route('categories.update', $category), ['name' => 'Intento externo'])
            ->assertForbidden();
    }

    public function test_inactive_category_remains_stored_and_no_delete_route_exists(): void
    {
        $category = Category::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $user = $this->administrator();

        $this->actingAs($user)->patch(route('categories.toggle', $category));
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'is_active' => false]);
        $this->actingAs($user)->delete('/categories/'.$category->id)->assertMethodNotAllowed();
        $this->assertNotNull($category->fresh());
    }

    private function administrator(): User
    {
        return User::factory()->create([
            'role_id' => $this->administratorRole->id,
            'branch_id' => $this->branch->id,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function cashier(array $attributes = []): User
    {
        return User::factory()->create([
            'role_id' => $this->cashierRole->id,
            'branch_id' => $this->branch->id,
            ...$attributes,
        ]);
    }
}
