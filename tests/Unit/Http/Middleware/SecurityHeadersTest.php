<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    private SecurityHeaders $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new SecurityHeaders();
    }

    /** @test */
    public function it_adds_security_headers_to_response(): void
    {
        // Act
        $response = $this->get('/api/v1/orders');

        // Assert
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Content-Security-Policy');
        
        // CORS Headers
        $response->assertHeader('Access-Control-Allow-Origin');
        $response->assertHeader('Access-Control-Allow-Methods');
        $response->assertHeader('Access-Control-Allow-Headers');
        $response->assertHeader('Access-Control-Allow-Credentials');
    }

    /** @test */
    public function it_allows_cors_preflight_requests(): void
    {
        // Act
        $response = $this->options('/api/v1/orders', [
            'HTTP_ORIGIN' => 'http://example.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        // Assert
        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->assertHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN');
    }

    /** @test */
    public function it_has_correct_content_security_policy(): void
    {
        // Act
        $response = $this->get('/api/v1/orders');
        
        // Get CSP header
        $csp = $response->headers->get('Content-Security-Policy');
        
        // Assert
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("style-src 'self'", $csp);
        $this->assertStringContainsString("img-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString('upgrade-insecure-requests', $csp);
    }

    /** @test */
    public function it_has_correct_permissions_policy(): void
    {
        // Act
        $response = $this->get('/api/v1/orders');
        
        // Get Permissions-Policy header
        $policy = $response->headers->get('Permissions-Policy');
        
        // Assert
        $this->assertStringContainsString('camera=()', $policy);
        $this->assertStringContainsString('geolocation=()', $policy);
        $this->assertStringContainsString('payment=()', $policy);
        $this->assertStringContainsString('microphone=()', $policy);
    }
}
