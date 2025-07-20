<x-layouts.app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 gap-4">
        {{-- Left Side - Complaints Table (2/3 width) --}}
        <div class="flex-1 overflow-hidden">
            <div class="h-full overflow-y-auto rounded-xl">
                <livewire:dashboard.complaints-table />
            </div>
        </div>

        {{-- Right Side - Chat Agent (1/3 width) --}}
        <div class="hidden w-full max-w-md lg:block">
            <flux:card class="h-full overflow-hidden">
                <livewire:dashboard.chat-agent />
            </flux:card>
        </div>
    </div>

    {{-- Mobile Chat Toggle --}}
    <div class="fixed bottom-4 right-4 lg:hidden">
        <flux:button
            x-data="{ open: false }"
            @click="open = true"
            variant="primary"
        >
            <flux:icon.chat-bubble-left-right />
            Chat with AI

            {{-- Mobile Chat Modal --}}
            <template x-teleport="body">
                <div
                    x-show="open"
                    @click.away="open = false"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center"
                >
                    <div
                        x-show="open"
                        x-transition:enter="ease-out duration-300"
                        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave="ease-in duration-200"
                        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        class="w-full max-w-lg transform overflow-hidden rounded-t-2xl bg-white shadow-xl transition-all sm:rounded-2xl dark:bg-gray-900"
                    >
                        <div class="flex items-center justify-between border-b border-gray-200 p-4 dark:border-gray-700">
                            <flux:heading size="lg">AI Assistant</flux:heading>
                            <flux:button variant="ghost" size="sm" @click="open = false">
                                <flux:icon.x-mark />
                            </flux:button>
                        </div>

                        <div class="h-[60vh] overflow-hidden">
                            <livewire:dashboard.chat-agent :key="'mobile-chat-' . now()" />
                        </div>
                    </div>
                </div>
            </template>
        </flux:button>
    </div>

    @push('scripts')
        <script src="{{ asset('js/dashboard.js') }}"></script>
    @endpush
</x-layouts.app>
