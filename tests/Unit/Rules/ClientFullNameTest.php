<?php

namespace Tests\Unit\Rules;

use App\Rules\ClientFullName;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClientFullNameTest extends TestCase
{
    #[DataProvider('validNames')]
    public function test_accepts_full_names(string $name): void
    {
        $this->assertTrue(ClientFullName::isValid($name));
    }

    #[DataProvider('invalidNames')]
    public function test_rejects_incomplete_names(string $name): void
    {
        $this->assertFalse(ClientFullName::isValid($name));
    }

    public function test_rule_fails_with_expected_message(): void
    {
        $failed = false;
        $message = null;
        (new ClientFullName)->validate('name', 'Maria', function (string $msg) use (&$failed, &$message): void {
            $failed = true;
            $message = $msg;
        });

        $this->assertTrue($failed);
        $this->assertSame(ClientFullName::MESSAGE, $message);
    }

    /** @return list<array{0: string}> */
    public static function validNames(): array
    {
        return [
            ['Maria Sousa'],
            ['Maria Joana Sousa'],
            ['  Ana  Costa  '],
            ['José Manuel'],
            ['Li Wei'],
        ];
    }

    /** @return list<array{0: string}> */
    public static function invalidNames(): array
    {
        return [
            [''],
            ['Maria'],
            ['Maria S.'],
            ['Maria S'],
            ['M. Sousa'],
            ['   Maria   '],
            ['A B'],
        ];
    }
}
