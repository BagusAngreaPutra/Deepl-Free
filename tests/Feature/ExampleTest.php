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
        $response->assertSee('Pilih atau tarik file DOCX dan PDF ke sini');
        $response->assertSee('multiple hidden', false);
        $response->assertSee('JDS Trasnlator');
        $response->assertDontSee('Gaya hasil');
        $response->assertSee('English (United States)');
        $response->assertSee('English (British)');
        $response->assertSee('Deutsch (Jerman)');
        $response->assertSee('中文 (Mandarin Sederhana)');
        $response->assertDontSee('Tambahkan kamus khusus');
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

    public function test_pdf_translate_requires_a_pdf_and_output_format(): void
    {
        $this->post('/translate-pdf', [], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pdf_file', 'output_format']);
    }
}
