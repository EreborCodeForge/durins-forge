<?php

declare(strict_types=1);

namespace App\Application\UseCases\User;

use App\Application\DTOs\User\CreateUserDTO;

interface CreateUserUseCaseInterface
{
    public function execute(CreateUserDTO $dto): mixed;
}