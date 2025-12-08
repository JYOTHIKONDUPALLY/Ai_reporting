<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Bizz AI Analytics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <!-- Logo and Title -->
            <div class="text-center mb-8">
                <div class="flex items-center justify-center gap-3 mb-4">
                    <div class="w-12 h-12 rounded-xl grid place-items-center bg-blue-50">
                        <img src="https://88tactical.com/wp-content/uploads/2022/07/88-tactical-logo-vert-236x300.png" alt="Logo" class="w-8 h-8">
                    </div>
                    <div class="text-left">
                        <h1 class="font-semibold">
                            <span style="color:#A71930; font-weight:bold;font-size:120%;">88 Tactical</span>
                            <span style="color:#333; font-weight:bold;font-size:120%;">AI</span>
                            <span style="color:#333; font-weight:500; font-size:80%;"> Analytics</span>
                        </h1>
                    </div>
                </div>
                <h2 class="text-2xl font-bold text-gray-900">Sign In</h2>
                <p class="text-gray-600 text-sm mt-2">Enter your credentials to access the dashboard</p>
            </div>

            <!-- Error Messages -->
            @if ($errors->has('credentials'))
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-red-800 text-sm">{{ $errors->first('credentials') }}</p>
                </div>
            @endif

            <!-- Login Form -->
            <form method="POST" action="{{ route('login') }}">
                @csrf
                
                <div class="mb-4">
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                        Username
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i data-lucide="user" class="w-5 h-5 text-gray-400"></i>
                        </div>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            value="{{ old('username') }}"
                            required 
                            autofocus
                            class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                            placeholder="Enter your username"
                        >
                    </div>
                </div>

                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        Password
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i data-lucide="lock" class="w-5 h-5 text-gray-400"></i>
                        </div>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required
                            class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                            placeholder="Enter your password"
                        >
                    </div>
                </div>

                <button 
                    type="submit" 
                    class="w-full py-2 px-4 rounded-lg text-white font-medium text-sm transition-colors"
                    style="background-color: #A71930;"
                    onmouseover="this.style.backgroundColor='#8b1424'"
                    onmouseout="this.style.backgroundColor='#A71930'"
                >
                    Sign In
                </button>
            </form>
        </div>

        <!-- Footer -->
        <p class="text-center text-gray-500 text-xs mt-6">
            © 2025 Bizz AI Analytics. All rights reserved.
        </p>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</body>
</html>

