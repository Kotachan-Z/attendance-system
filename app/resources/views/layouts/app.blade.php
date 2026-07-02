<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <title>@yield('title', '出席管理システム')</title>
    <style>[x-cloak]{display:none !important}</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen font-sans antialiased text-gray-800">
    <nav x-data="{ mobileOpen: false }" class="bg-white border-b border-gray-200 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">

                {{-- ロゴ --}}
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 flex-shrink-0">
                    <img src="{{ asset('images/favicon.png') }}" alt="" class="w-8 h-8">
                    <span class="text-lg font-bold text-gray-800 tracking-wide">出席管理システム</span>
                </a>

                {{-- デスクトップナビ --}}
                <div class="hidden md:flex items-center gap-1 text-sm font-medium">
                    @php
                        $navLink = fn(bool $active) => $active
                            ? 'px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 font-semibold'
                            : 'px-3 py-1.5 rounded-lg text-gray-500 hover:text-gray-800 hover:bg-gray-100 transition';
                    @endphp

                    <a href="{{ route('dashboard') }}" class="{{ $navLink(request()->routeIs('dashboard')) }}">ホーム</a>
                    <a href="{{ route('sessions.index') }}" class="{{ $navLink(request()->routeIs('sessions.*')) }}">出席記録</a>
                    <a href="{{ route('students.index') }}" class="{{ $navLink(request()->routeIs('students.*')) }}">学生</a>
                    <a href="{{ route('courses.index') }}" class="{{ $navLink(request()->routeIs('courses.*')) }}">授業</a>
                    @if (Auth::user()->isAdmin())
                        <a href="{{ route('schedules.index') }}" class="{{ $navLink(request()->routeIs('schedules.*')) }}">時間割</a>

                        {{-- 管理ドロップダウン --}}
                        <div x-data="{ open: false }" @click.outside="open = false" class="relative">
                            <button @click="open = !open"
                                    class="{{ $navLink(request()->routeIs('detections.*') || request()->routeIs('admin.*')) }} flex items-center gap-1">
                                管理
                                <svg class="w-3.5 h-3.5 transition-transform" :class="open && 'rotate-180'"
                                     fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div x-show="open" x-cloak x-transition.origin.top
                                 class="absolute right-0 mt-2 w-48 bg-white text-gray-700 rounded-xl shadow-lg border border-gray-100 py-1.5 z-50">
                                <a href="{{ route('detections.index') }}"
                                   class="block px-4 py-2.5 text-sm hover:bg-gray-50 {{ request()->routeIs('detections.*') ? 'text-indigo-600 font-semibold' : '' }}">
                                    検出ログ
                                    <span class="block text-xs text-gray-400 font-normal">なりすまし・未登録者の記録</span>
                                </a>
                                <a href="{{ route('admin.teachers.index') }}"
                                   class="block px-4 py-2.5 text-sm hover:bg-gray-50 {{ request()->routeIs('admin.*') ? 'text-indigo-600 font-semibold' : '' }}">
                                    教員管理
                                    <span class="block text-xs text-gray-400 font-normal">教職員アカウントの追加・削除</span>
                                </a>
                            </div>
                        </div>
                    @endif

                    {{-- ログイン中ユーザー --}}
                    <div class="flex items-center gap-3 pl-4 ml-2 border-l border-gray-200 whitespace-nowrap">
                        @if (Auth::user()->isAdmin())
                            <span class="bg-amber-100 text-amber-700 text-xs font-semibold rounded-full px-2.5 py-0.5">管理者</span>
                        @else
                            <span class="bg-sky-100 text-sky-700 text-xs font-semibold rounded-full px-2.5 py-0.5">教員</span>
                        @endif
                        <a href="{{ route('profile.edit') }}"
                           class="text-gray-600 text-xs font-medium hover:text-gray-900 hover:underline">{{ Auth::user()->name }}</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                    class="text-xs text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded px-2 py-1">
                                ログアウト
                            </button>
                        </form>
                    </div>
                </div>

                {{-- モバイル: ハンバーガー --}}
                <button @click="mobileOpen = !mobileOpen" class="md:hidden p-2 rounded-lg text-gray-500 hover:bg-gray-100">
                    <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg x-show="mobileOpen" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- モバイルメニュー --}}
        <div x-show="mobileOpen" x-cloak x-transition.origin.top class="md:hidden border-t border-gray-100 bg-white">
            @php
                $mobLink = fn(bool $active) => $active
                    ? 'block px-4 py-3 bg-indigo-50 text-indigo-700 font-semibold'
                    : 'block px-4 py-3 text-gray-600 hover:bg-gray-50';
            @endphp
            <a href="{{ route('dashboard') }}" class="{{ $mobLink(request()->routeIs('dashboard')) }}">ホーム</a>
            <a href="{{ route('sessions.index') }}" class="{{ $mobLink(request()->routeIs('sessions.*')) }}">出席記録</a>
            <a href="{{ route('students.index') }}" class="{{ $mobLink(request()->routeIs('students.*')) }}">学生</a>
            <a href="{{ route('courses.index') }}" class="{{ $mobLink(request()->routeIs('courses.*')) }}">授業</a>
            @if (Auth::user()->isAdmin())
                <a href="{{ route('schedules.index') }}" class="{{ $mobLink(request()->routeIs('schedules.*')) }}">時間割</a>
                <p class="px-4 pt-3 pb-1 text-xs text-gray-400 font-semibold uppercase">管理</p>
                <a href="{{ route('detections.index') }}" class="{{ $mobLink(request()->routeIs('detections.*')) }}">検出ログ</a>
                <a href="{{ route('admin.teachers.index') }}" class="{{ $mobLink(request()->routeIs('admin.*')) }}">教員管理</a>
            @endif
            <div class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                <div class="flex items-center gap-2">
                    @if (Auth::user()->isAdmin())
                        <span class="bg-amber-100 text-amber-700 text-xs font-semibold rounded-full px-2.5 py-0.5">管理者</span>
                    @else
                        <span class="bg-sky-100 text-sky-700 text-xs font-semibold rounded-full px-2.5 py-0.5">教員</span>
                    @endif
                    <a href="{{ route('profile.edit') }}" class="text-xs font-medium text-gray-600 hover:underline">{{ Auth::user()->name }}</a>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-xs text-gray-400 hover:text-gray-700">ログアウト</button>
                </form>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if (session('success'))
            <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-lg px-4 py-3">
                {{ session('error') }}
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
