<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityControllerTest extends WebTestCase
{
    public function testLoginPageIncludesCsrfAndRememberMeFields(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="_csrf_token"]');
        $this->assertSelectorExists('input[name="_remember_me"]');
    }

    public function testInvalidLoginReturnsToLoginWithoutAuthenticating(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Sign In')->form();
        $form['username'] = 'no-such-user-'.bin2hex(random_bytes(6));
        $form['password'] = 'invalid-password';

        $client->submit($form);

        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorExists('.alert-error');
    }

    public function testForgotPasswordForUnknownAddressUsesGenericMessage(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/forgot-password');
        $form = $crawler->selectButton('Send reset link')->form();
        $form['form[email]'] = 'no-such-user-'.bin2hex(random_bytes(6)).'@example.invalid';

        $client->submit($form);

        $this->assertResponseRedirects('/forgot-password');
        $client->followRedirect();
        $this->assertSelectorTextContains('[role="status"]', 'If that email exists, reset instructions have been sent.');
    }

    public function testResetPageRejectsMissingToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reset-password?token=invalid');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'This password reset link is invalid or has expired.');
        $this->assertSelectorNotExists('form');
    }
}
