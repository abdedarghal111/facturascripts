<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2017-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Test\Core;

use FacturaScripts\Core\Cache;
use FacturaScripts\Core\Session;
use PHPUnit\Framework\TestCase;

/**
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
final class SessionTest extends TestCase
{
    public function testSet(): void
    {
        // añadimos un valor a la sesión
        $key = 'test-key';
        $value = '1234';
        Session::set($key, $value);

        // comprobamos que se ha añadido
        $this->assertEquals($value, Session::get($key), 'session-value-not-found');

        // comprobamos que se puede cambiar el valor
        Session::set($key, '5678');
        $this->assertEquals('5678', Session::get($key), 'session-value-not-changed');
    }

    public function testGetNull(): void
    {
        // comprobamos que devuelve null si no existe
        $this->assertNull(Session::get('not-found'), 'session-value-not-null');
    }

    public function testGetClientIp(): void
    {
        // comprobamos que la llamada devuelve ::1
        $this->assertEquals('::1', Session::getClientIp(), 'session-ip-not-found');
    }

    public function testFormTokenReturnsNonEmptyString(): void
    {
        $token = Session::formToken();
        $this->assertNotEmpty($token, 'form-token-empty');
    }

    public function testFormTokenHasCorrectFormat(): void
    {
        $token = Session::formToken();
        $parts = explode('|', $token);
        $this->assertCount(2, $parts, 'form-token-invalid-format');
        $this->assertEquals(40, strlen($parts[0]), 'form-token-hash-length');
        $this->assertNotEmpty($parts[1], 'form-token-random-empty');
    }

    public function testFormTokenGeneratesDifferentTokensEachTime(): void
    {
        $token1 = Session::formToken();
        $token2 = Session::formToken();
        $this->assertNotEquals($token1, $token2, 'form-token-not-unique');
    }

    public function testFormTokenUserSpecificReturnsNonEmptyString(): void
    {
        $token = Session::formToken(true);
        $this->assertNotEmpty($token, 'form-token-user-specific-empty');
    }

    public function testValidateFormTokenReturnsFalseForEmptyToken(): void
    {
        $this->assertFalse(Session::validateFormToken(''), 'validate-empty-token-should-fail');
    }

    public function testValidateFormTokenReturnsFalseForInvalidToken(): void
    {
        $this->assertFalse(Session::validateFormToken('invalid-token'), 'validate-invalid-token-should-fail');
    }

    public function testValidateFormTokenReturnsFalseForMalformedToken(): void
    {
        $this->assertFalse(Session::validateFormToken('abc|def'), 'validate-malformed-token-should-fail');
    }

    public function testValidateFormTokenReturnsTrueForValidToken(): void
    {
        Cache::clear();
        $token = Session::formToken();
        $this->assertTrue(Session::validateFormToken($token), 'validate-valid-token-should-pass');
    }

    public function testValidateFormTokenReturnsFalseForDuplicateToken(): void
    {
        Cache::clear();
        $token = Session::formToken();
        Session::validateFormToken($token);
        $this->assertFalse(Session::validateFormToken($token), 'validate-duplicate-token-should-fail');
    }

    public function testValidateFormTokenUserSpecificReturnsTrueForValidToken(): void
    {
        Cache::clear();
        $token = Session::formToken(true);
        $this->assertTrue(Session::validateFormToken($token, true), 'validate-user-specific-token-should-pass');
    }

    public function testNonUserSpecificTokenFailsUserSpecificValidation(): void
    {
        Cache::clear();
        $tokenGeneral = Session::formToken(false);
        $tokenUserSpecific = Session::formToken(true);
        $this->assertNotEquals($tokenGeneral, $tokenUserSpecific, 'user-specific-token-should-differ');
    }
}
