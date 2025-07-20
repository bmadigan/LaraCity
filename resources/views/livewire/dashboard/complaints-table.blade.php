<div class="w-full space-y-4">
    {{-- Stats Cards --}}
    <div class="grid gap-4 md:grid-cols-4">
        <flux:card>
            <div class="flex items-center justify-between p-4">
                <div>
                    <flux:subheading>Total Complaints</flux:subheading>
                    <flux:heading size="xl">{{ number_format($stats['total']) }}</flux:heading>
                </div>
                <flux:spacer />
                <flux:icon.inbox class="size-8" />
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between p-4">
                <div>
                    <flux:subheading>Open</flux:subheading>
                    <flux:heading size="xl">{{ number_format($stats['open']) }}</flux:heading>
                </div>
                <flux:spacer />
                <flux:icon.check-circle class="size-8" />
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between p-4">
                <div>
                    <flux:subheading>Escalated</flux:subheading>
                    <flux:heading size="xl">{{ number_format($stats['escalated']) }}</flux:heading>
                </div>
                <flux:spacer />
                <flux:icon.exclamation-triangle class="size-8" />
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between p-4">
                <div>
                    <flux:subheading>Closed</flux:subheading>
                    <flux:heading size="xl">{{ number_format($stats['closed']) }}</flux:heading>
                </div>
                <flux:spacer />
                <flux:icon.lock-closed class="size-8" />
            </div>
        </flux:card>
    </div>

    {{-- Filters --}}
    <flux:card class="p-4">
        <div class="grid gap-4 md:grid-cols-5">
            <flux:input 
                wire:model.live.debounce.300ms="search" 
                placeholder="Search complaints..."
                type="search"
                class="md:col-span-2"
            >
                <x-slot name="iconLeading">
                    <flux:icon.magnifying-glass />
                </x-slot>
            </flux:input>

            <flux:select wire:model.live="status" placeholder="All Statuses">
                <option value="">All Statuses</option>
                @foreach($statuses as $statusOption)
                    <option value="{{ $statusOption }}">{{ $statusOption }}</option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="borough" placeholder="All Boroughs">
                <option value="">All Boroughs</option>
                @foreach($boroughs as $boroughOption)
                    <option value="{{ $boroughOption }}">{{ $boroughOption }}</option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="riskLevel" placeholder="All Risk Levels">
                <option value="">All Risk Levels</option>
                @foreach($riskLevels as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </flux:select>
        </div>

        @if($search || $status || $borough || $riskLevel)
            <div class="mt-3 flex items-center gap-2">
                <flux:text>Active filters:</flux:text>
                <flux:button variant="ghost" size="sm" wire:click="clearFilters">
                    Clear all
                </flux:button>
            </div>
        @endif
    </flux:card>

    {{-- Complaints Table --}}
    <flux:card class="overflow-hidden">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-zinc-50 dark:bg-zinc-800">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        <flux:button variant="ghost" size="sm" wire:click="sortBy('complaint_number')" class="flex items-center gap-1">
                            Complaint #
                            @if($sortBy === 'complaint_number')
                                <flux:icon.{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }} class="h-3 w-3" />
                            @endif
                        </flux:button>
                    </th>
                    
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        <flux:button variant="ghost" size="sm" wire:click="sortBy('complaint_type')" class="flex items-center gap-1">
                            Type
                            @if($sortBy === 'complaint_type')
                                <flux:icon.{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }} class="h-3 w-3" />
                            @endif
                        </flux:button>
                    </th>
                    
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        Borough
                    </th>
                    
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        <flux:button variant="ghost" size="sm" wire:click="sortBy('status')" class="flex items-center gap-1">
                            Status
                            @if($sortBy === 'status')
                                <flux:icon.{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }} class="h-3 w-3" />
                            @endif
                        </flux:button>
                    </th>
                    
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        Risk Level
                    </th>
                    
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        <flux:button variant="ghost" size="sm" wire:click="sortBy('created_at')" class="flex items-center gap-1">
                            Created
                            @if($sortBy === 'created_at')
                                <flux:icon.{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }} class="h-3 w-3" />
                            @endif
                        </flux:button>
                    </th>
                    
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        Actions
                    </th>
                </tr>
            </thead>

            <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-zinc-900">
                @forelse($complaints as $complaint)
                    <tr wire:key="complaint-{{ $complaint->id }}">
                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $complaint->complaint_number }}
                        </td>
                        
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $complaint->complaint_type }}
                        </td>
                        
                        <td class="whitespace-nowrap px-6 py-4 text-sm">
                            <flux:badge size="sm" color="blue">{{ $complaint->borough }}</flux:badge>
                        </td>
                        
                        <td class="whitespace-nowrap px-6 py-4 text-sm">
                            <flux:badge 
                                size="sm" 
                                :color="match($complaint->status) {
                                    'Open' => 'green',
                                    'InProgress' => 'blue',
                                    'Escalated' => 'red',
                                    'Closed' => 'zinc',
                                    default => 'blue'
                                }"
                            >
                                {{ $complaint->status }}
                            </flux:badge>
                        </td>
                        
                        <td class="whitespace-nowrap px-6 py-4 text-sm">
                            @if($complaint->analysis)
                                @php
                                    $riskScore = $complaint->analysis->risk_score;
                                    $riskLevel = $riskScore >= 0.7 ? 'High' : ($riskScore >= 0.4 ? 'Medium' : 'Low');
                                    $riskVariant = $riskScore >= 0.7 ? 'danger' : ($riskScore >= 0.4 ? 'warning' : 'success');
                                @endphp
                                <flux:badge size="sm" :color="$riskScore >= 0.7 ? 'red' : ($riskScore >= 0.4 ? 'yellow' : 'green')">
                                    {{ $riskLevel }} ({{ number_format($riskScore, 2) }})
                                </flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">Pending</flux:badge>
                            @endif
                        </td>
                        
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ \Carbon\Carbon::parse($complaint->created_at)->diffForHumans() }}
                        </td>
                        
                        <td class="whitespace-nowrap px-6 py-4 text-sm">
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm">
                                    <flux:icon.ellipsis-horizontal class="h-4 w-4" />
                                </flux:button>
                                
                                <flux:menu>
                                    <flux:menu.item icon="eye">View Details</flux:menu.item>
                                    <flux:menu.item icon="magnifying-glass">Find Similar</flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="exclamation-triangle" variant="danger">Escalate</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                            No complaints found matching your criteria.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Pagination --}}
        @if($complaints->hasPages())
            <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-700">
                {{ $complaints->links() }}
            </div>
        @endif
    </flux:card>
</div>