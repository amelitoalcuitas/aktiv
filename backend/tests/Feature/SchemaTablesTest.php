<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_one_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('hubs'));
        $this->assertTrue(Schema::hasTable('courts'));
        $this->assertTrue(Schema::hasTable('court_sports'));
        $this->assertTrue(Schema::hasTable('hub_sports'));
        $this->assertTrue(Schema::hasTable('app_settings'));
    }

    public function test_hub_approval_defaults_are_applied(): void
    {
        $columns = Schema::getColumns('hubs');
        $isApproved = collect($columns)->firstWhere('name', 'is_approved');
        $isVerified = collect($columns)->firstWhere('name', 'is_verified');

        $this->assertNotNull($isApproved);
        $this->assertNotNull($isVerified);
        $this->assertSame('1', trim((string) ($isApproved['default'] ?? ''), "'"));
        $this->assertSame('0', trim((string) ($isVerified['default'] ?? ''), "'"));
    }
}
