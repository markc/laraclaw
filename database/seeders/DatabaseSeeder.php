<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->create([
            'name' => 'Mark Constable',
            'email' => 'markc@renta.net',
            'password' => bcrypt('changeme_N0W'),
        ]);

        $this->call(AgentSeeder::class);
    }
}
