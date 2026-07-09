<div x-data="{ open: true }" class="flex h-screen bg-gray-100 dark:bg-gray-900 overflow-hidden">
    <!-- Sidebar -->
    <div class="w-64 bg-indigo-900 dark:bg-gray-800 text-white flex flex-col shadow-2xl relative">
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
                   class="flex items-center px-6 py-3 text-indigo-100 hover:bg-indigo-800 dark:hover:bg-gray-700 transition-colors group {{ request()->routeIs('webcurso.importar') ? 'bg-indigo-800 dark:bg-gray-700' : '' }}">
                    <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                    <span x-show="open" class="ml-4 whitespace-nowrap">Importar Archivos</span>
                </a>

                <a href="{{ route('webcurso.encomienda') }}" wire:navigate
                   class="flex items-center px-6 py-3 text-indigo-100 hover:bg-indigo-800 dark:hover:bg-gray-700 transition-colors group {{ request()->routeIs('webcurso.encomienda') ? 'bg-indigo-800 dark:bg-gray-700' : '' }}">
                    <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <span x-show="open" class="ml-4 whitespace-nowrap">Encomiendas</span>
                </a>

                <a href="{{ route('webcurso.participantes-bonificados') }}" wire:navigate
                   class="flex items-center px-6 py-3 text-indigo-100 hover:bg-indigo-800 dark:hover:bg-gray-700 transition-colors group {{ request()->routeIs('webcurso.participantes-bonificados') ? 'bg-indigo-800 dark:bg-gray-700' : '' }}">
                    <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    <span x-show="open" class="ml-4 whitespace-nowrap">Part. Bonificados</span>
                </a>

                <a href="{{ route('webcurso.acciones-formativas') }}" wire:navigate
                   class="flex items-center px-6 py-3 text-indigo-100 hover:bg-indigo-800 dark:hover:bg-gray-700 transition-colors group {{ request()->routeIs('webcurso.acciones-formativas') ? 'bg-indigo-800 dark:bg-gray-700' : '' }}">
                    <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                    <span x-show="open" class="ml-4 whitespace-nowrap">Acciones Formativas</span>
                </a>

                <a href="{{ route('webcurso.tutores') }}" wire:navigate
                   class="flex items-center px-6 py-3 text-indigo-100 hover:bg-indigo-800 dark:hover:bg-gray-700 transition-colors group {{ request()->routeIs('webcurso.tutores') ? 'bg-indigo-800 dark:bg-gray-700' : '' }}">
                    <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    <span x-show="open" class="ml-4 whitespace-nowrap">Tutores</span>
                </a>

                <a href="{{ route('webcurso.alumnos') }}" wire:navigate
                   class="flex items-center px-6 py-3 text-indigo-100 hover:bg-indigo-800 dark:hover:bg-gray-700 transition-colors group {{ request()->routeIs('webcurso.alumnos') ? 'bg-indigo-800 dark:bg-gray-700' : '' }}">
                    <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <span x-show="open" class="ml-4 whitespace-nowrap">Alumnos</span>
                </a>

                <a href="{{ route('webcurso.reportes-moodle') }}" wire:navigate
                   class="flex items-center px-6 py-3 text-indigo-100 hover:bg-indigo-800 dark:hover:bg-gray-700 transition-colors group {{ request()->routeIs('webcurso.reportes-moodle') ? 'bg-indigo-800 dark:bg-gray-700' : '' }}">
                    <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    <span x-show="open" class="ml-4 whitespace-nowrap">Reportes Moodle</span>
                </a>

                <a href="{{ route('webcurso.zoho.books') }}" wire:navigate
                   class="flex items-center px-6 py-3 text-indigo-100 hover:bg-indigo-800 dark:hover:bg-gray-700 transition-colors group {{ request()->routeIs('webcurso.zoho.*') ? 'bg-indigo-800 dark:bg-gray-700' : '' }}">
                    <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <span x-show="open" class="ml-4 whitespace-nowrap">Zoho Books</span>
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
    </div>

    <!-- Main Content -->
    <div class="flex-grow flex flex-col overflow-y-auto">
        {{ $slot }}
    </div>
</div>