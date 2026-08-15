<?php

namespace Tests\Feature;

use App\Contracts\DatabaseBackupCreator;
use App\Data\BackupResult;
use App\Enums\RoleSlug;
use App\Models\BackupSetting;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Services\AutomaticBackupService;
use App\Services\BackupManager;
use App\Services\MySqlDatabaseBackupCreator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Mockery;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Role $administratorRole;

    private Role $cashierRole;

    protected function setUp(): void
    {
        parent::setUp();
        config(['backup.automatic_enabled' => false]);
        $this->branch = Branch::factory()->create();
        $this->administratorRole = Role::factory()->create(['slug' => RoleSlug::Administrator->value]);
        $this->cashierRole = Role::factory()->create(['slug' => RoleSlug::Cashier->value]);
    }

    public function test_only_administrator_can_start_manual_backup(): void
    {
        $manager = Mockery::mock(BackupManager::class);
        $manager->shouldReceive('run')->once()->andReturn(BackupResult::successful('backup.sql.gz', 'C:\\QuiQueMicromarket\\Backups\\backup.sql.gz'));
        $this->app->instance(BackupManager::class, $manager);

        $this->actingAs($this->administrator())->postJson(route('admin.backup.store'))->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', "El respaldo se guardó en:\nC:\\QuiQueMicromarket\\Backups\n\nArchivo: backup.sql.gz");
        $this->actingAs($this->cashier())->postJson(route('admin.backup.store'))->assertForbidden();
    }

    public function test_manual_backup_modal_has_confirmation_loading_and_result_states(): void
    {
        $this->actingAs($this->administrator())->get(route('dashboard'))->assertOk()
            ->assertSee('Realizar backup')->assertSee('¿Está seguro de que desea realizar una copia de seguridad del sistema?')
            ->assertSee('Realizando copia de seguridad')->assertSee('backupPhase === \'loading\'', false);
        $this->actingAs($this->cashier())->get(route('dashboard'))->assertOk()->assertDontSee('Realizar backup');
    }

    public function test_database_creator_runs_full_dump_and_generates_valid_gzip(): void
    {
        $directory = storage_path('framework/testing/backup-'.bin2hex(random_bytes(4)));
        config(['backup.local_path' => $directory, 'backup.mysqldump_path' => 'mysqldump-test']);
        Process::fake(function (PendingProcess $process) {
            $resultArgument = collect($process->command)->first(fn ($argument) => str_starts_with($argument, '--result-file='));
            File::put(substr($resultArgument, strlen('--result-file=')), "-- complete database\nCREATE TABLE test (id INT);\n");

            return Process::result();
        });
        try {
            $backup = app(MySqlDatabaseBackupCreator::class)->create();
            $this->assertFileExists($backup['path']);
            $this->assertStringContainsString('CREATE TABLE test', gzdecode(File::get($backup['path'])));
            $this->assertSame(hash_file('sha256', $backup['path']), $backup['sha256']);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $backup['sha256']);
            Process::assertRan(function (PendingProcess $process) {
                $command = $process->command;

                return in_array('--single-transaction', $command, true) && in_array('--routines', $command, true)
                    && in_array('--events', $command, true) && in_array('--hex-blob', $command, true)
                    && end($command) === config('database.connections.mysql.database')
                    && collect($command)->doesntContain(fn ($argument) => str_starts_with($argument, '--ignore-table'));
            });
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_mysqldump_error_is_detected_and_no_backup_is_published(): void
    {
        $directory = storage_path('framework/testing/backup-'.bin2hex(random_bytes(4)));
        config(['backup.local_path' => $directory]);
        Process::fake([Process::result(errorOutput: 'fallo', exitCode: 1)]);
        try {
            $this->expectException(\RuntimeException::class);
            app(MySqlDatabaseBackupCreator::class)->create();
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_successful_local_backup_updates_last_successful_backup(): void
    {
        $this->travelTo(Carbon::parse('2026-08-15 10:00:00'));
        $result = $this->managerWithSuccessfulLocalBackup()->run();
        $this->assertTrue($result->isSuccessful());
        $this->assertTrue(BackupSetting::current()->last_successful_backup_at->equalTo(now()));
    }

    public function test_local_failure_does_not_update_success(): void
    {
        $creator = Mockery::mock(DatabaseBackupCreator::class);
        $creator->shouldReceive('create')->once()->andThrow(new \RuntimeException('dump falló'));
        $this->assertSame('failed', (new BackupManager($creator))->run()->status);
        $this->assertNull(BackupSetting::current()->last_successful_backup_at);
    }

    public function test_concurrent_backup_is_rejected(): void
    {
        $lock = Cache::lock('database-backup-generation', 60);
        $this->assertTrue($lock->get());
        $creator = Mockery::mock(DatabaseBackupCreator::class);
        $creator->shouldNotReceive('create');

        try {
            $this->assertSame('failed', (new BackupManager($creator))->run()->status);
        } finally {
            $lock->release();
        }
    }

    public function test_automatic_backup_is_due_only_after_seven_days(): void
    {
        config(['backup.automatic_enabled' => true, 'backup.automatic_interval_days' => 7]);
        $this->travelTo(Carbon::parse('2026-08-15 10:00:00'));
        $this->assertSame(1, BackupSetting::current()->id);
        BackupSetting::current()->update(['last_successful_backup_at' => now()->subDays(6)]);
        $service = new AutomaticBackupService(Mockery::mock(BackupManager::class));
        $this->assertFalse($service->isDue());
        BackupSetting::current()->update(['last_successful_backup_at' => now()->subDays(7)->subSecond()]);
        $this->assertTrue($service->isDue());
    }

    public function test_successful_automatic_backup_restarts_interval(): void
    {
        config(['backup.automatic_enabled' => true]);
        BackupSetting::current()->update(['last_successful_backup_at' => now()->subDays(8)]);
        $service = new AutomaticBackupService($this->managerWithSuccessfulLocalBackup());
        $this->assertTrue($service->runIfDue()?->isSuccessful());
        $this->assertFalse($service->isDue());
    }

    public function test_failed_automatic_backup_does_not_restart_success_interval_and_is_throttled(): void
    {
        config(['backup.automatic_enabled' => true, 'backup.automatic_retry_minutes' => 60]);
        BackupSetting::current()->update(['last_successful_backup_at' => now()->subDays(8)]);
        $manager = Mockery::mock(BackupManager::class);
        $manager->shouldReceive('run')->once()->andReturn(BackupResult::failed('error técnico'));
        $service = new AutomaticBackupService($manager);
        $this->assertSame('failed', $service->runIfDue()?->status);
        $this->assertTrue(BackupSetting::current()->last_successful_backup_at->lessThan(now()->subDays(7)));
        $this->assertFalse($service->isDue());
        $this->travel(61)->minutes();
        $this->assertTrue($service->isDue());
    }

    public function test_due_backup_redirects_login_to_blocking_process_screen(): void
    {
        config(['backup.automatic_enabled' => true]);
        $service = Mockery::mock(AutomaticBackupService::class);
        $service->shouldReceive('isDue')->twice()->andReturn(true);
        $this->app->instance(AutomaticBackupService::class, $service);
        $user = $this->administrator(['password' => bcrypt('password')]);
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('backup.automatic.show'));
        $this->actingAs($user)->get(route('backup.automatic.show'))->assertOk()->assertSee('Realizando copia de seguridad')->assertSee('x-init="run()"', false);
    }

    public function test_automatic_result_is_detailed_for_admin_and_sanitized_for_cashier(): void
    {
        config(['backup.automatic_enabled' => true]);
        foreach ([[$this->administrator(), true], [$this->cashier(), false]] as [$user, $administrator]) {
            BackupSetting::query()->delete();
            $manager = Mockery::mock(BackupManager::class);
            $manager->shouldReceive('run')->once()->andReturn(BackupResult::successful('backup_2026-08-15_10-00-00.sql.gz', 'C:\\QuiQueMicromarket\\Backups\\backup_2026-08-15_10-00-00.sql.gz', str_repeat('a', 64), now()->toIso8601String()));
            $this->app->instance(BackupManager::class, $manager);
            $response = $this->actingAs($user)->postJson(route('backup.automatic.store'))->assertOk();
            if ($administrator) {
                $response->assertJsonPath('status', 'success')->assertJsonPath('title', 'Copia de seguridad automática')
                    ->assertJsonFragment(['message' => "Se realizó correctamente la copia de seguridad automática del sistema.\n\nRuta: C:\\QuiQueMicromarket\\Backups\nArchivo: backup_2026-08-15_10-00-00.sql.gz\nFecha y hora: ".now()->format('d/m/Y H:i:s')]);
            } else {
                $response->assertJsonPath('message', 'Se realizó correctamente la copia de seguridad del sistema.')
                    ->assertJsonMissing(['C:\\QuiQueMicromarket'])->assertJsonMissing(['backup_2026']);
            }
        }
    }

    private function managerWithSuccessfulLocalBackup(): BackupManager
    {
        $creator = Mockery::mock(DatabaseBackupCreator::class);
        $creator->shouldReceive('create')->once()->andReturn(['path' => 'C:\\QuiQueMicromarket\\Backups\\backup.sql.gz', 'filename' => 'backup.sql.gz', 'sha256' => str_repeat('a', 64)]);

        return new BackupManager($creator);
    }

    private function administrator(array $attributes = []): User
    {
        return User::factory()->create(['branch_id' => $this->branch->id, 'role_id' => $this->administratorRole->id, ...$attributes]);
    }

    private function cashier(): User
    {
        return User::factory()->create(['branch_id' => $this->branch->id, 'role_id' => $this->cashierRole->id]);
    }
}
