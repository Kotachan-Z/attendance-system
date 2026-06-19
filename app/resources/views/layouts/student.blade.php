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
<body class="bg-gray-100 min-h-screen font-sans antialiased">
    <nav class="bg-emerald-700 text-white shadow">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <span class="text-xl font-bold tracking-wide">生徒ポータル</span>
                <div class="flex items-center gap-4 text-sm font-medium">
                    <span class="text-emerald-200 text-xs">{{ Auth::guard('student')->user()->name }}</span>
                    <a href="{{ route('student.password.edit') }}"
                       class="text-xs text-emerald-200 hover:text-white hover:underline">パスワード変更</a>
                    <form method="POST" action="{{ route('student.logout') }}">
                        @csrf
                        <button type="submit" class="text-xs text-emerald-200 hover:text-white hover:underline">
                            ログアウト
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if (session('success'))
            <div class="mb-4 bg-green-100 border border-green-400 text-green-800 rounded px-4 py-3">
                {{ session('success') }}
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
