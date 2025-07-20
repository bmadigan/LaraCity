<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Complaint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ComplaintsTable extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';
    
    #[Url]
    public string $status = '';
    
    #[Url]
    public string $borough = '';
    
    #[Url]
    public string $riskLevel = '';
    
    #[Url]
    public string $sortBy = 'created_at';
    
    #[Url]
    public string $sortDirection = 'desc';
    
    public int $perPage = 10;

    public function mount(): void
    {
        // Initialize component
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedBorough(): void
    {
        $this->resetPage();
    }

    public function updatedRiskLevel(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'desc';
        }
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->borough = '';
        $this->riskLevel = '';
        $this->resetPage();
    }

    public function render(): View
    {
        $complaints = Complaint::with(['analysis', 'actions'])
            ->when($this->search, function (Builder $query) {
                $query->where(function (Builder $q) {
                    $q->where('complaint_number', 'like', "%{$this->search}%")
                        ->orWhere('complaint_type', 'like', "%{$this->search}%")
                        ->orWhere('descriptor', 'like', "%{$this->search}%")
                        ->orWhere('location_type', 'like', "%{$this->search}%");
                });
            })
            ->when($this->status, fn(Builder $query) => $query->where('status', $this->status))
            ->when($this->borough, fn(Builder $query) => $query->where('borough', $this->borough))
            ->when($this->riskLevel, function (Builder $query) {
                $query->whereHas('analysis', function (Builder $q) {
                    switch ($this->riskLevel) {
                        case 'high':
                            $q->where('risk_score', '>=', 0.7);
                            break;
                        case 'medium':
                            $q->whereBetween('risk_score', [0.4, 0.7]);
                            break;
                        case 'low':
                            $q->where('risk_score', '<', 0.4);
                            break;
                    }
                });
            })
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate($this->perPage);

        $stats = [
            'total' => Complaint::count(),
            'open' => Complaint::where('status', 'Open')->count(),
            'escalated' => Complaint::where('status', 'Escalated')->count(),
            'closed' => Complaint::where('status', 'Closed')->count(),
        ];

        return view('livewire.dashboard.complaints-table', [
            'complaints' => $complaints,
            'stats' => $stats,
            'statuses' => ['Open', 'In Progress', 'Escalated', 'Closed'],
            'boroughs' => ['MANHATTAN', 'BROOKLYN', 'QUEENS', 'BRONX', 'STATEN ISLAND'],
            'riskLevels' => [
                'high' => 'High Risk (≥0.7)',
                'medium' => 'Medium Risk (0.4-0.7)',
                'low' => 'Low Risk (<0.4)'
            ]
        ]);
    }
}