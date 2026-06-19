<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <title>@yield('title', '出席管理システム')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 min-h-screen font-sans antialiased">
    <nav class="bg-indigo-700 text-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="{{ route('dashboard') }}" class="text-xl font-bold tracking-wide">出席管理システム</a>
                <div class="flex items-center gap-6 text-sm font-medium">
                    <a href="{{ route('dashboard') }}" class="hover:text-indigo-200">ダッシュボード</a>
                    <a href="{{ route('students.index') }}" class="hover:text-indigo-200">学生</a>
                    <a href="{{ route('courses.index') }}" class="hover:text-indigo-200">授業</a>
                    @if (Auth::user()->isAdmin())
                    <a href="{{ route('schedules.index') }}" class="hover:text-indigo-200">スケジュール</a>
                    @endif
                    <a href="{{ route('sessions.index') }}" class="hover:text-indigo-200">セッション</a>
                    @if (Auth::user()->isAdmin())
                    <a href="{{ route('detections.index') }}" class="hover:text-indigo-200">検出ログ</a>
                    <a href="{{ route('admin.teachers.index') }}" class="hover:text-indigo-200">教員管理</a>
                    @endif

                    {{-- ログイン中ユーザー（役割を色分けして表示） --}}
                    <div class="flex items-center gap-3 pl-5 border-l border-indigo-500 whitespace-nowrap">
                        @if (Auth::user()->isAdmin())
                            <span class="bg-amber-400 text-amber-900 text-xs font-bold rounded-full px-2.5 py-0.5">管理者</span>
                        @else
                            <span class="bg-sky-300 text-sky-900 text-xs font-bold rounded-full px-2.5 py-0.5">教員</span>
                        @endif
                        <span class="text-white text-xs font-medium">{{ Auth::user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                    class="text-xs text-indigo-200 hover:text-white hover:bg-indigo-600 rounded px-2 py-1">
                                ログアウト
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if (session('success'))
            <div class="mb-4 bg-green-100 border border-green-400 text-green-800 rounded px-4 py-3">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-4 bg-red-100 border border-red-400 text-red-800 rounded px-4 py-3">
                {{ session('error') }}
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
