<?php

namespace App\Contracts;

interface DatabaseBackupCreator
{
    /** @return array{path:string,filename:string,sha256:string} */
    public function create(): array;
}
