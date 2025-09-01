<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function fetch() {
        return User::all();
    }
}
