<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserEmailHelpersTest extends TestCase
{
    #[DataProvider('syntheticEmailProvider')]
    public function test_is_synthetic_email(mixed $email, bool $expected): void
    {
        $this->assertSame($expected, User::isSyntheticEmail($email));
    }

    /**
     * @return array<string, array{0: mixed, 1: bool}>
     */
    public static function syntheticEmailProvider(): array
    {
        return [
            'null' => [null, false],
            'false (LGPD decrypt edge)' => [false, false],
            'empty' => ['', false],
            'int' => [1, false],
            'array' => [['x'], false],
            'real email' => ['pessoa@paroquia.org', false],
            'login.local' => ['monitor@login.local', true],
            'equipe.local' => ['casal@equipe.local', true],
            'case insensitive' => ['Monitor@Login.Local', true],
        ];
    }
}
