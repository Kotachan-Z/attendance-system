<x-guest-layout>
    <div class="mb-4 text-center">
        <h1 class="text-lg font-bold text-gray-800">生徒ログイン</h1>
        <p class="text-xs text-gray-500 mt-1">学籍番号とパスワードを入力してください</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('student.login') }}">
        @csrf

        {{-- 学籍番号 --}}
        <div>
            <x-input-label for="student_number" value="学籍番号" />
            <x-text-input id="student_number" class="block mt-1 w-full" type="text" name="student_number"
                          :value="old('student_number')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('student_number')" class="mt-2" />
        </div>

        {{-- パスワード --}}
        <div class="mt-4">
            <x-input-label for="password" value="パスワード" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password"
                          required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        {{-- ログイン保持 --}}
        <div class="block mt-4">
            <label for="remember" class="inline-flex items-center">
                <input id="remember" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">ログインを保持する</span>
            </label>
        </div>

        <div class="flex items-center justify-between mt-6">
            <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ route('login') }}">
                教職員の方はこちら
            </a>
            <x-primary-button>ログイン</x-primary-button>
        </div>
    </form>
</x-guest-layout>
