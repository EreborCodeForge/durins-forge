<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\User;
use App\Domain\Enums\UserRole;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Infrastructure\Exceptions\InfrastructureException;
use EreborCodeForge\Mazarbul\Query\Database;
use Throwable;

class PDOUserRepository implements UserRepositoryInterface
{
    public function __construct(private Database $db) {}

    public function findByEmail(string $email): ?User
    {
        try {
            $row = $this->db->fetchOne('SELECT * FROM users WHERE email = ?', [$email]);

            return $row ? $this->mapRowToEntity($row) : null;
        } catch (Throwable $e) {
            throw new InfrastructureException('Failed to find user by email', 0, $e);
        }
    }

    public function findById(int $id): ?User
    {
        try {
            $row = $this->db->fetchOne('SELECT * FROM users WHERE id = ?', [$id]);

            return $row ? $this->mapRowToEntity($row) : null;
        } catch (Throwable $e) {
            throw new InfrastructureException('Failed to find user by id', 0, $e);
        }
    }

    public function findAll(): array
    {
        try {
            $rows = $this->db->fetchAll('SELECT * FROM users');
            $users = [];
            foreach ($rows as $row) {
                $users[] = $this->mapRowToEntity($row);
            }

            return $users;
        } catch (Throwable $e) {
            throw new InfrastructureException('Failed to fetch all users', 0, $e);
        }
    }

    public function save(User $user): User
    {
        try {
            $this->db->execute(
                'INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)',
                [
                    $user->name,
                    $user->email,
                    $user->password,
                    $user->role->value,
                ]
            );
            $user->id = (int) $this->db->lastInsertId();

            return $user;
        } catch (Throwable $e) {
            throw new InfrastructureException('Failed to save user', 0, $e);
        }
    }

    public function update(User $user): bool
    {
        try {
            $this->db->execute(
                'UPDATE users SET name = ?, email = ?, password = ?, role = ? WHERE id = ?',
                [
                    $user->name,
                    $user->email,
                    $user->password,
                    $user->role->value,
                    $user->id,
                ]
            );

            return true;
        } catch (Throwable $e) {
            throw new InfrastructureException('Failed to update user', 0, $e);
        }
    }

    public function delete(int $id): bool
    {
        try {
            $this->db->execute('DELETE FROM users WHERE id = ?', [$id]);

            return true;
        } catch (Throwable $e) {
            throw new InfrastructureException('Failed to delete user', 0, $e);
        }
    }

    private function mapRowToEntity(array $row): User
    {
        return new User(
            id: (int) $row['id'],
            name: $row['name'],
            email: $row['email'],
            password: $row['password'],
            role: UserRole::from($row['role'])
        );
    }
}
