<?php

namespace Database\Seeders;

use App\Models\Tenant\Settings;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class settingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Settings::insert([
            [
                'key_name' => 'site_title',
                'value' => 'My Blog',
                'type' =>  'string',
                'description' => 'Website title',
                'is_public'  => TRUE,
                'group_name' => 'general'
            ],
            [
                'key_name' => 'site_description',
                'value' => 'A great blog powered by our platform',
                'type' =>  'string',
                'description' => 'Website description',
                'is_public'  => TRUE,
                'group_name' => 'general'
            ],
            [
                'key_name' => 'posts_per_page',
                'value' => '10',
                'type' =>  'number',
                'description' => 'Number of posts per page',
                'is_public'  => TRUE,
                'group_name' => 'content'
            ],
            [
                'key_name' => 'allow_comments',
                'value' => 'true',
                'type' =>  'boolean',
                'description' => 'Allow comments on posts',
                'is_public'  => TRUE,
                'group_name' => 'content'
            ],
            [
                'key_name' => 'comment_moderation',
                'value' => 'true',
                'type' =>  'boolean',
                'description' => 'Require comment approval',
                'is_public'  => FALSE,
                'group_name' => 'content'
            ],
            [
                'key_name' => 'theme',
                'value' => 'default',
                'type' =>  'string',
                'description' => 'Active theme',
                'is_public'  => TRUE,
                'group_name' => 'appearance'
            ],
            [
                'key_name' => 'timezone',
                'value' => 'UTC',
                'type' =>  'string',
                'description' => 'Site timezone',
                'is_public'  => TRUE,
                'group_name' => 'general'
            ],
        ]);
    }
}
