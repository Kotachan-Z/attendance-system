<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <title>@yield('title', '生徒ポータル')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen font-sans antialiased text-gray-800">
    <nav class="bg-white border-b border-gray-200 shadow-sm">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <span class="flex items-center gap-2">
                    <img src="{{ asset('images/favicon.png') }}" alt="" class="w-8 h-8">
                    <span class="text-lg font-bold text-gray-800 tracking-wide">生徒ポータル</span>
                </span>
                <div class="flex items-center gap-4 text-sm font-medium">
                    <span class="text-gray-500 text-xs">{{ Auth::guard('student')->user()->name }}</span>
                    <a href="{{ route('student.password.edit') }}"
                       class="text-xs text-gray-500 hover:text-gray-800 hover:underline">パスワード変更</a>
                    <form method="POST" action="{{ route('student.logout') }}">
                        @csrf
                        <button type="submit" class="text-xs text-gray-400 hover:text-gray-700 hover:underline">
                            ログアウト
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if (session('success'))
            <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3">
                {{ session('success') }}
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
