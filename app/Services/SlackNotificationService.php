<?php

namespace App\Services;

use App\Models\Action;
use App\Models\Complaint;
use App\Models\ComplaintAnalysis;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackNotificationService
{
    private ?string $webhookUrl;

    public function __construct()
    {
        $this->webhookUrl = config('laracity.slack.webhook_url');
    }

    /**
     * Send high-risk complaint alert to Slack
     */
    public function sendEscalationAlert(
        Complaint $complaint,
        ComplaintAnalysis $analysis,
        Action $escalationAction
    ): bool {
        if (empty($this->webhookUrl)) {
            Log::warning('Slack webhook URL not configured, skipping notification', [
                'complaint_id' => $complaint->id,
            ]);
            return false;
        }

        Log::info('Sending Slack escalation alert', [
            'complaint_id' => $complaint->id,
            'risk_score' => $analysis->risk_score,
        ]);

        $message = $this->buildEscalationMessage($complaint, $analysis, $escalationAction);

        try {
            $response = Http::timeout(10)->post($this->webhookUrl, $message);

            if ($response->successful()) {
                Log::info('Slack notification sent successfully', [
                    'complaint_id' => $complaint->id,
                    'response_status' => $response->status(),
                ]);
                return true;
            } else {
                Log::error('Slack notification failed', [
                    'complaint_id' => $complaint->id,
                    'response_status' => $response->status(),
                    'response_body' => $response->body(),
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error('Slack notification exception', [
                'complaint_id' => $complaint->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send a test message to verify Slack integration
     */
    public function sendTestMessage(): array
    {
        if (empty($this->webhookUrl)) {
            return [
                'success' => false,
                'message' => 'Slack webhook URL not configured',
            ];
        }

        $message = [
            'text' => '🧪 LaraCity Test Notification',
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => '*LaraCity System Test* 🏙️\n\nSlack integration is working correctly!'
                    ]
                ],
                [
                    'type' => 'context',
                    'elements' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => sprintf('Sent at: %s', now()->format('Y-m-d H:i:s T'))
                        ]
                    ]
                ]
            ]
        ];

        try {
            $response = Http::timeout(10)->post($this->webhookUrl, $message);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Test notification sent successfully',
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Slack API error: ' . $response->body(),
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Build formatted Slack message for escalation alert
     */
    private function buildEscalationMessage(
        Complaint $complaint,
        ComplaintAnalysis $analysis,
        Action $escalationAction
    ): array {
        $riskEmoji = $this->getRiskEmoji($analysis->risk_score);
        $urgencyLevel = $this->getUrgencyLevel($analysis->risk_score);
        
        // Condense summary for Slack (max 200 chars as per requirements)
        $condensedSummary = $this->condenseSummary($analysis->summary);

        return [
            'text' => sprintf('🚨 High-Risk Complaint Alert: %s (%s)', $complaint->complaint_type, $complaint->borough),
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => sprintf('🚨 %s Risk Complaint Alert', $urgencyLevel),
                    ]
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => sprintf('*Complaint #:*\n%s', $complaint->complaint_number)
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => sprintf('*Risk Score:*\n%s %.2f', $riskEmoji, $analysis->risk_score)
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => sprintf('*Type:*\n%s', $complaint->complaint_type)
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => sprintf('*Location:*\n%s', $complaint->borough)
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => sprintf('*Agency:*\n%s', $complaint->agency_name ?? $complaint->agency)
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => sprintf('*Category:*\n%s', $analysis->category)
                        ]
                    ]
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => sprintf('*AI Summary:*\n%s', $condensedSummary)
                    ]
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => sprintf('*Address:*\n%s', $complaint->incident_address ?? 'Not specified')
                    ]
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => sprintf('*Submitted:*\n%s', $complaint->submitted_at?->format('M j, Y g:i A'))
                    ]
                ],
                [
                    'type' => 'divider'
                ],
                [
                    'type' => 'context',
                    'elements' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => sprintf(
                                'Escalated automatically | Threshold: %.1f | Tags: %s',
                                config('complaints.escalate_threshold', 0.7),
                                implode(', ', $analysis->tags ?? [])
                            )
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Get emoji based on risk score
     */
    private function getRiskEmoji(float $riskScore): string
    {
        if ($riskScore >= 0.9) return '🔴';
        if ($riskScore >= 0.8) return '🟠';
        if ($riskScore >= 0.7) return '🟡';
        return '🟢';
    }

    /**
     * Get urgency level text based on risk score
     */
    private function getUrgencyLevel(float $riskScore): string
    {
        if ($riskScore >= 0.9) return 'CRITICAL';
        if ($riskScore >= 0.8) return 'HIGH';
        return 'ELEVATED';
    }

    /**
     * Condense AI summary to fit Slack message limits (<200 chars)
     */
    private function condenseSummary(string $summary): string
    {
        if (strlen($summary) <= 200) {
            return $summary;
        }

        // Try to truncate at sentence boundary
        $truncated = substr($summary, 0, 180);
        $lastPeriod = strrpos($truncated, '.');
        
        if ($lastPeriod !== false && $lastPeriod > 100) {
            return substr($summary, 0, $lastPeriod + 1);
        }

        // Fallback: truncate with ellipsis
        return substr($summary, 0, 190) . '...';
    }
}