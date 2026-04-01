<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::create([
            'name' => 'Demo Company',
            'slug' => 'demo',
            'wp_site_url' => 'http://localhost:8082',
            'wp_api_key' => Str::random(64),
            'wp_api_endpoint' => 'http://localhost:8082/wp-json/sakusaku/v1',
            'docs_retrieval_method' => 'url_direct',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(30),
            'notification_events' => ['on_submit' => true, 'on_publish' => true],
        ]);

        $admin = User::create([
            'tenant_id' => $tenant->id,
            'google_id' => 'demo_admin_001',
            'email' => 'admin@demo.com',
            'name' => 'Demo Admin',
            'role' => 'admin',
            'level' => 3,
        ]);

        $poster = User::create([
            'tenant_id' => $tenant->id,
            'google_id' => 'demo_poster_001',
            'email' => 'poster@demo.com',
            'name' => 'Demo Poster',
            'role' => 'poster',
            'level' => 1,
        ]);

        $categories = [];
        foreach (['お知らせ', 'ブログ', '施工事例'] as $i => $name) {
            $categories[] = Category::create([
                'tenant_id' => $tenant->id,
                'wp_category_id' => $i + 1,
                'name' => $name,
                'slug' => Str::slug($name) ?: "cat-{$i}",
                'sort_order' => $i,
            ]);
        }

        foreach (['pending', 'draft', 'published'] as $i => $status) {
            Post::create([
                'tenant_id' => $tenant->id,
                'user_id' => $poster->id,
                'google_doc_id' => "demo_doc_00{$i}",
                'google_doc_url' => "https://docs.google.com/document/d/demo_doc_00{$i}/edit",
                'title' => "サンプル記事 " . ($i + 1),
                'status' => $status,
                'category_id' => $categories[$i]->id,
                'published_at' => $status === 'published' ? now() : null,
                'published_by' => $status === 'published' ? $admin->id : null,
            ]);
        }
    }
}
