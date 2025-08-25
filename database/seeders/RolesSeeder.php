<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::insert([
            [
                'name' => 'Owner',
                'slug' => 'owner',
                'description' => 'Full access to everything',
                'permissions' => json_encode(['*']),
                'is_default' => false
            ],
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Manage users, posts, and settings',
                'permissions' => json_encode(["users.*", "posts.*", "categories.*", "settings.read", "settings.write"]),
                'is_default' => false
            ],
            [
                'name' => 'Editor',
                'slug' => 'editor',
                'description' => 'Create and edit all posts',
                'permissions' => json_encode(["posts.*", "categories.read", "media.*"]),
                'is_default' => false
            ],
            [
                'name' => 'Author',
                'slug' => 'author',
                'description' => 'Create and edit own posts',
                'permissions' => json_encode(["posts.create", "posts.edit_own", "posts.delete_own", "media.upload"]),
                'is_default' => TRUE
            ],
            [
                'name' => 'Contributor',
                'slug' => 'contributor',
                'description' => 'Submit posts for review',
                'permissions' => json_encode(["posts.create_draft", "media.upload"]),
                'is_default' => FALSE
            ],
        ]);
    }
}
