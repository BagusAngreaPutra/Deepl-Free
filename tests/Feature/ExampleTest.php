<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Terjemahkan banyak file DOCX sekaligus.');
        $response->assertSee('multiple hidden', false);
        $response->assertSee('Standard British English');
    }

    public function test_health_endpoint_returns_ok(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_translate_requires_a_docx_file_as_json(): void
    {
        $this->post('/translate', [], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('docx_file');
    }

    public function test_ocr_requires_an_image_as_json(): void
    {
        $this->post('/ocr-translate', [], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image_file');
    }
}
