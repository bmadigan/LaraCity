<div class="flex h-full flex-col">
    {{-- Chat Header --}}
    <div class="border-b border-gray-200 p-4 dark:border-gray-700">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="relative">
                    <flux:icon.chat-bubble-left-right class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                    <div class="absolute -right-1 -top-1 h-2 w-2 rounded-full bg-green-500"></div>
                </div>
                <flux:heading size="lg">AI Assistant</flux:heading>
            </div>
            
            <flux:button variant="ghost" size="sm" wire:click="clearChat" wire:confirm="Are you sure you want to clear the chat history?">
                <flux:icon.trash class="h-4 w-4" />
            </flux:button>
        </div>
    </div>

    {{-- Chat Messages --}}
    <div 
        class="flex-1 overflow-y-auto p-4 space-y-4"
        data-chat-container
    >
        @foreach($messages as $message)
            <div class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-[80%] space-y-1">
                    @if($message['role'] === 'assistant')
                        <flux:text class="text-xs text-gray-500">AI Assistant</flux:text>
                    @endif
                    
                    <div class="rounded-lg px-4 py-2 {{ $message['role'] === 'user' ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-gray-800' }}">
                        @if(isset($message['isStreaming']) && $message['isStreaming'])
                            <div class="flex items-start gap-2">
                                <div class="prose prose-sm max-w-none {{ $message['role'] === 'user' ? 'text-white' : 'dark:prose-invert' }}">
                                    {!! \Illuminate\Support\Str::markdown($message['content'] ?: '...') !!}
                                </div>
                                <flux:icon.sparkles class="h-4 w-4 animate-pulse text-blue-500" />
                            </div>
                        @else
                            <div class="prose prose-sm max-w-none {{ $message['role'] === 'user' ? 'text-white' : 'dark:prose-invert' }}">
                                {!! \Illuminate\Support\Str::markdown($message['content']) !!}
                            </div>
                        @endif
                        
                        @if(isset($message['isError']) && $message['isError'])
                            <div class="mt-2 flex items-center gap-1 text-red-600 dark:text-red-400">
                                <flux:icon.exclamation-circle class="h-4 w-4" />
                                <flux:text class="text-xs">Error occurred</flux:text>
                            </div>
                        @endif
                    </div>
                    
                    <flux:text class="text-xs text-gray-400">
                        {{ \Carbon\Carbon::parse($message['timestamp'])->format('g:i a') }}
                    </flux:text>
                </div>
            </div>
        @endforeach

        @if($isProcessing && !$messages->last()['isStreaming'])
            <div class="flex justify-start">
                <div class="max-w-[80%] space-y-1">
                    <flux:text class="text-xs text-gray-500">AI Assistant</flux:text>
                    <div class="rounded-lg bg-gray-100 px-4 py-2 dark:bg-gray-800">
                        <div class="flex items-center gap-2">
                            <flux:icon.arrow-path class="h-4 w-4 animate-spin text-gray-500" />
                            <flux:text class="text-sm text-gray-500">Thinking...</flux:text>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Message Input --}}
    <div class="border-t border-gray-200 p-4 dark:border-gray-700">
        <form wire:submit.prevent="sendMessage" class="flex gap-2">
            <flux:input 
                wire:model.defer="userMessage"
                type="text"
                placeholder="Ask about complaints, search for patterns, or get insights... (Ctrl+Enter to send)"
                class="flex-1"
                :disabled="$isProcessing"
                autofocus
            >
                <x-slot name="iconLeading">
                    <flux:icon.chat-bubble-left />
                </x-slot>
            </flux:input>
            
            <flux:button type="submit" :disabled="$isProcessing">
                @if($isProcessing)
                    <flux:icon.arrow-path />
                @else
                    <flux:icon.paper-airplane />
                @endif
                Send
            </flux:button>
        </form>
        
        <div class="mt-2 flex flex-wrap gap-2">
            <flux:text class="text-xs text-gray-500">Try asking:</flux:text>
            <flux:badge 
                size="sm" 
                variant="neutral" 
                class="cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700"
                wire:click="$set('userMessage', 'Show me high-risk complaints in Manhattan')"
            >
                High-risk complaints
            </flux:badge>
            <flux:badge 
                size="sm" 
                variant="neutral" 
                class="cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700"
                wire:click="$set('userMessage', 'What are the most common complaint types?')"
            >
                Common complaints
            </flux:badge>
            <flux:badge 
                size="sm" 
                variant="neutral" 
                class="cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700"
                wire:click="$set('userMessage', 'Find noise complaints in Brooklyn')"
            >
                Search by area
            </flux:badge>
        </div>
    </div>
</div>

