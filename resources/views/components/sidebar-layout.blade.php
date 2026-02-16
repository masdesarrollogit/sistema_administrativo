<div x-data="{ open: false }" class="flex h-screen bg-gray-100 dark:bg-gray-900 overflow-hidden">
    <!-- Sidebar -->
    <div :class="open ? 'w-64' : 'w-20'"
        class="bg-indigo-900 dark:bg-gray-800 text-white transition-all duration-300 ease-in-out flex flex-col shadow-2xl relative">
        <!-- Logo Area -->
        <div class="h-16 flex items-center px-6 border-b border-indigo-800 dark:border-gray-700">
            <div class="bg-white p-2 rounded-lg">
                <svg class="w-6 h-6 text-indigo-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
            </div>
            <span x-show="open" x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 transform -translate-x-4"
                x-transition:enter-end="opacity-100 transform translate-x-0"
                class="ml-3 font-bold text-xl tracking-wider">MASDES</span>
        </div>

        <!-- Navigation -->
        <nav class="flex-grow py-6 space-y-2">
            <a href="{{ route('dashboard') }}" wire:navigate
                class="flex items-center px-6 py-3 text-indigo-100 hover:bg-indigo-800 dark:hover:bg-gray-700 transition-colors group {{ request()->routeIs('dashboard') ? 'bg-indigo-800 dark:bg-gray-700' : '' }}">
                <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                <span x-show="open" class="ml-4 transition-all whitespace-nowrap">Dashboard</span>
            </a>

            @hasanyrole('admin|SuperAdmin')
                <div class="px-6 py-2 mt-4 mb-2" x-show="open">
                    <p class="text-xs font-semibold text-indigo-300 uppercase tracking-wider">WebCurso</p>
                </div>

                <a href="{{ route('webcurso.empresas') }}" wire:navigate
                   class="flex items-center px-6 py-3 text-indigo-100 hover:bg-indigo-800 dark:hover:bg-gray-700 transition-colors group {{ request()->routeIs('webcurso.empresas*') ? 'bg-indigo-800 dark:bg-gray-700' : '' }}">
                    <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    <span x-show="open" class="ml-4 whitespace-nowrap">Empresas</span>
                </a>

                <a href="{{ route('webcurso.grupos') }}" wire:navigate
                   class="flex items-center px-6 py-3 text-indigo-100 hover:bg-indigo-800 dark:hover:bg-gray-700 transition-colors group {{ request()->routeIs('webcurso.grupos*') ? 'bg-indigo-800 dark:bg-gray-700' : '' }}">
                    <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    <span x-show="open" class="ml-4 whitespace-nowrap">Grupos</span>
                </a>

                <a href="{{ route('webcurso.candidatos.index') }}" wire:navigate
                   class="flex items-center px-6 py-3 text-indigo-100 hover:bg-indigo-800 dark:hover:bg-gray-700 transition-colors group {{ request()->routeIs('webcurso.candidatos*') ? 'bg-indigo-800 dark:bg-gray-700' : '' }}">
                    <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                    <span x-show="open" class="ml-4 whitespace-nowrap">Candidatos</span>
                </a>

                <a href="{{ route('webcurso.importar') }}" wire:navigate
                   class="flex items-center px-6 py-3 text-indigo-100 hover:bg-indigo-800 dark:hover:bg-gray-700 transition-colors group {{ request()->routeIs('webcurso.importar*') ? 'bg-indigo-800 dark:bg-gray-700' : '' }}">
                    <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                    <span x-show="open" class="ml-4 whitespace-nowrap">Importar CSV</span>
                </a>
            @endhasanyrole

            <div class="border-t border-indigo-800 dark:border-gray-700 my-2"></div>

            <a href="#"
                class="flex items-center px-6 py-3 text-indigo-100 hover:bg-indigo-800 dark:hover:bg-gray-700 transition-colors group">
                <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                    </path>
                </svg>
                <span x-show="open" class="ml-4 whitespace-nowrap">Moodle Support</span>
            </a>
        </nav>

        <!-- Toggle Button -->
        <button @click="open = !open"
            class="absolute -right-3 top-20 bg-indigo-600 rounded-full p-1 border-2 border-white dark:border-gray-900 transition-transform hover:scale-110 active:scale-90">
            <svg :class="open ? 'rotate-0' : 'rotate-180'" class="w-4 h-4 text-white transition-transform duration-300"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
        </button>
    </div>

    <!-- Main Content -->
    <div class="flex-grow flex flex-col overflow-y-auto">
        {{ $slot }}
    </div>
</div>