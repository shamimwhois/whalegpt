<?php

use Illuminate\Support\Facades\Route;

// The chat app is the product's front door; the API it talks to lives under
// the /ai prefix (see routes/chat.php).
Route::redirect('/', '/ai/chat')->name('home');
