<div class="relative flex min-h-screen w-full items-center justify-center overflow-hidden lg:justify-end lg:pr-20">
    <img
        src="{{ asset('images/fondo-campo-trigo.webp') }}"
        alt=""
        class="absolute inset-0 h-full w-full object-cover"
    >
    <div class="absolute inset-0 bg-black/10"></div>

    <div class="relative z-10 hidden flex-1 items-center justify-center px-8 lg:flex">
        <img
            src="{{ asset('images/logo-cosechar-blend.png') }}"
            alt="Cosechar E.I.R.L. — Cultivando Seguridad, Cosechando Bienestar"
            class="w-full max-w-3xl"
            style="mask-image: radial-gradient(ellipse 60% 60% at center, black 45%, transparent 85%); -webkit-mask-image: radial-gradient(ellipse 60% 60% at center, black 45%, transparent 85%);"
        >
    </div>

    <div class="relative z-10 m-6 w-full max-w-lg">
        <div class="rounded-2xl bg-white p-10 shadow-2xl">
            <div class="mb-6 flex justify-center lg:hidden">
                <img
                    src="{{ asset('images/logo-cosechar-blend.png') }}"
                    alt="Cosechar E.I.R.L."
                    class="w-80"
                    style="mask-image: radial-gradient(ellipse 60% 60% at center, black 45%, transparent 85%); -webkit-mask-image: radial-gradient(ellipse 60% 60% at center, black 45%, transparent 85%);"
                >
            </div>

            <h1 class="mb-6 text-xl font-semibold text-gray-900">Ingresar a COSECHAR Inventario</h1>

        <form wire:submit="autenticar" class="flex flex-col gap-4">
            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-gray-700">Correo</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0-.828.672-1.5 1.5-1.5h16.5c.828 0 1.5.672 1.5 1.5v10.5a1.5 1.5 0 0 1-1.5 1.5H3.75a1.5 1.5 0 0 1-1.5-1.5V6.75Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="m3 7 8.445 5.63a1 1 0 0 0 1.11 0L21 7" />
                        </svg>
                    </span>
                    <input
                        type="email"
                        id="email"
                        wire:model="email"
                        autofocus
                        placeholder="ejemplo@correo.com"
                        class="w-full rounded-md border border-gray-300 py-2 pl-10 pr-3 text-sm shadow-sm focus:border-green-700 focus:ring-green-700"
                    >
                </div>
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div x-data="{ mostrar: false }">
                <label for="password" class="mb-1 block text-sm font-medium text-gray-700">Contraseña</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                    </span>
                    <input
                        :type="mostrar ? 'text' : 'password'"
                        id="password"
                        wire:model="password"
                        class="w-full rounded-md border border-gray-300 py-2 pl-10 pr-10 text-sm shadow-sm focus:border-green-700 focus:ring-green-700"
                    >
                    <button
                        type="button"
                        @click="mostrar = !mostrar"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600"
                    >
                        <svg x-show="!mostrar" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                        <svg x-show="mostrar" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    </button>
                </div>
                @error('password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" wire:model="remember" class="rounded border border-gray-300 text-green-800 focus:ring-green-700">
                Recordarme
            </label>

            <button
                type="submit"
                wire:loading.attr="disabled"
                class="rounded-md bg-green-800 px-4 py-2 text-sm font-medium text-white hover:bg-green-900 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <span wire:loading.remove>Ingresar</span>
                <span wire:loading>Verificando...</span>
            </button>
        </form>
        </div>
    </div>
</div>
