<?php
/**
 * ATIERA Financial Management System - Workflow Automation
 * Comprehensive workflow engine for automated business processes
 */

class WorkflowEngine {
    private static $instance = null;
    private $db;
    private $workflows = [];
    private $triggers = [];

    private function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->initializeWorkflows();
        $this->initializeTriggers();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize predefined workflows
     */
    private function initializeWorkflows() {
        $this->workflows = [
            'invoice_approval' => [
                'name' => 'Invoice Approval Workflow',
                'description' => 'Multi-step approval process for invoices over threshold',
                'trigger' => 'invoice.created',
                'conditions' => [
                    ['field' => 'total_amount', 'operator' => '>', 'value' => 50000]
                ],
                'steps' => [
                    [
                        'name' => 'Admin Approval',
                        'type' => 'approval',
                        'assignee_role' => 'admin',
                        'timeout_hours' => 24,
                        'actions' => [
                            'approve' => ['status' => 'approved'],
                            'reject' => ['status' => 'rejected']
                        ]
                    ],
                    [
                        'name' => 'Admin Review',
                        'type' => 'approval',
                        'assignee_role' => 'admin',
                        'timeout_hours' => 48,
                        'actions' => [
                            'approve' => ['status' => 'approved'],
                            'reject' => ['status' => 'rejected']
                        ]
                    ]
                ]
            ],

            'bill_payment' => [
                'name' => 'Bill Payment Workflow',
                'description' => 'Automated bill payment processing',
                'trigger' => 'bill.approved',
                'conditions' => [],
                'steps' => [
                    [
                        'name' => 'Schedule Payment',
                        'type' => 'action',
                        'action' => 'schedule_payment',
                        'delay_days' => 0,
                        'conditions' => [
                            ['field' => 'payment_terms', 'operator' => '==', 'value' => 'Net 30']
                        ]
                    ]
                ]
            ],

            'overdue_invoice' => [
                'name' => 'Overdue Invoice Management',
                'description' => 'Automated handling of overdue invoices',
                'trigger' => 'invoice.overdue',
                'conditions' => [],
                'steps' => [
                    [
                        'name' => 'Escalate to Collections',
                        'type' => 'action',
                        'action' => 'create_task',
                        'delay_days' => 7,
                        'assignee_role' => 'admin',
                        'task_title' => 'Follow up on overdue invoice',
                        'task_priority' => 'high'
                    ]
                ]
            ],

            'budget_alert' => [
                'name' => 'Budget Alert System',
                'description' => 'Alerts when budget thresholds are exceeded',
                'trigger' => 'transaction.posted',
                'conditions' => [
                    ['field' => 'budget_exceeded', 'operator' => '==', 'value' => true]
                ],
                'steps' => [
                    [
                        'name' => 'Freeze Transactions',
                        'type' => 'action',
                        'action' => 'require_approval',
                        'delay_days' => 0,
                        'approval_required' => true
                    ]
                ]
            ],

            'new_customer_onboarding' => [
                'name' => 'Customer Onboarding',
                'description' => 'Automated customer welcome and setup process',
                'trigger' => 'customer.created',
                'conditions' => [],
                'steps' => [
                    [
                        'name' => 'Create Credit Application',
                        'type' => 'action',
                        'action' => 'create_task',
                        'assignee_role' => 'admin',
                        'task_title' => 'Review credit application',
                        'task_priority' => 'medium'
                    ],
                    [
                        'name' => 'Setup Customer Portal',
                        'type' => 'action',
                        'action' => 'send_portal_invite',
                        'delay_days' => 1
                    ]
                ]
            ]
        ];
    }

    /**
     * Initialize event triggers
     */
    private function initializeTriggers() {
        $this->triggers = [
            'invoice.created' => 'InvoiceCreated',
            'invoice.updated' => 'InvoiceUpdated',
            'invoice.paid' => 'InvoicePaid',
            'invoice.overdue' => 'InvoiceOverdue',
            'bill.created' => 'BillCreated',
            'bill.approved' => 'BillApproved',
            'bill.paid' => 'BillPaid',
            'payment.received' => 'PaymentReceived',
            'payment.made' => 'PaymentMade',
            'customer.created' => 'CustomerCreated',
            'customer.updated' => 'CustomerUpdated',
            'vendor.created' => 'VendorCreated',
            'transaction.posted' => 'TransactionPosted',
            'user.login' => 'UserLogin',
            'user.failed_login' => 'UserFailedLogin'
        ];
    }

    /**
     * Trigger workflow execution
     */
    public function triggerWorkflow($eventType, $data = []) {
        try {
            // Find workflows that match this trigger
            $matchingWorkflows = $this->findMatchingWorkflows($eventType, $data);

            foreach ($matchingWorkflows as $workflowId => $workflow) {
                $this->executeWorkflow($workflowId, $workflow, $data);
            }

            // Log the trigger
            Logger::getInstance()->info("Workflow trigger executed: $eventType", [
                'data' => $data,
                'matching_workflows' => count($matchingWorkflows)
            ]);

        } catch (Exception $e) {
            Logger::getInstance()->error("Workflow trigger failed: $eventType", [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
        }
    }

    /**
     * Find workflows that match the trigger and conditions
     */
    private function findMatchingWorkflows($eventType, $data) {
        $matching = [];

        foreach ($this->workflows as $workflowId => $workflow) {
            if ($workflow['trigger'] === $eventType) {
                // Check conditions
                if ($this->checkConditions($workflow['conditions'], $data)) {
                    $matching[$workflowId] = $workflow;
                }
            }
        }

        return $matching;
    }

    /**
     * Check if workflow conditions are met
     */
    private function checkConditions($conditions, $data) {
        foreach ($conditions as $condition) {
            $field = $condition['field'];
            $operator = $condition['operator'];
            $value = $condition['value'];

            $actualValue = $this->getNestedValue($data, $field);

            if (!$this->evaluateCondition($actualValue, $operator, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get nested value from data array
     */
    private function getNestedValue($data, $field) {
        $keys = explode('.', $field);
        $value = $data;

        foreach ($keys as $key) {
            if (isset($value[$key])) {
                $value = $value[$key];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Evaluate condition
     */
    private function evaluateCondition($actual, $operator, $expected) {
        switch ($operator) {
            case '==':
            case '=':
                return $actual == $expected;
            case '!=':
            case '<>':
                return $actual != $expected;
            case '>':
                return $actual > $expected;
            case '<':
                return $actual < $expected;
            case '>=':
                return $actual >= $expected;
            case '<=':
                return $actual <= $expected;
            case 'contains':
                return strpos($actual, $expected) !== false;
            case 'in':
                return in_array($actual, (array)$expected);
            default:
                return false;
        }
    }

    /**
     * Execute workflow
     */
    private function executeWorkflow($workflowId, $workflow, $data) {
        try {
            // Create workflow instance
            $instanceId = $this->createWorkflowInstance($workflowId, $data);

            // Execute steps
            foreach ($workflow['steps'] as $stepIndex => $step) {
                $this->executeWorkflowStep($instanceId, $stepIndex, $step, $data);
            }

        } catch (Exception $e) {
            Logger::getInstance()->error("Workflow execution failed: $workflowId", [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
        }
    }

    /**
     * Create workflow instance
     */
    private function createWorkflowInstance($workflowId, $data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO workflow_instances (workflow_id, trigger_data, status, created_at)
                VALUES (?, ?, 'running', NOW())
            ");
            $stmt->execute([$workflowId, json_encode($data)]);
            return $this->db->lastInsertId();

        } catch (Exception $e) {
            throw new Exception("Failed to create workflow instance: " . $e->getMessage());
        }
    }

    /**
     * Execute workflow step
     */
    private function executeWorkflowStep($instanceId, $stepIndex, $step, $data) {
        try {
            $stepId = $this->createWorkflowStep($instanceId, $stepIndex, $step);

            switch ($step['type']) {
                case 'approval':
                    $this->executeApprovalStep($stepId, $step, $data);
                    break;
                case 'action':
                    $this->executeActionStep($stepId, $step, $data);
                    break;

                case 'delay':
                    $this->scheduleDelayedStep($stepId, $step, $data);
                    break;
            }

        } catch (Exception $e) {
            Logger::getInstance()->error("Workflow step execution failed", [
                'instance_id' => $instanceId,
                'step_index' => $stepIndex,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create workflow step record
     */
    private function createWorkflowStep($instanceId, $stepIndex, $step) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO workflow_steps (instance_id, step_index, step_name, step_type, status, created_at)
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$instanceId, $stepIndex, $step['name'], $step['type']]);
            return $this->db->lastInsertId();

        } catch (Exception $e) {
            throw new Exception("Failed to create workflow step: " . $e->getMessage());
        }
    }

    /**
     * Execute approval step
     */
    private function executeApprovalStep($stepId, $step, $data) {
        try {
            // Find assignee based on role
            $assigneeId = $this->findAssigneeByRole($step['assignee_role'], $data);

            if ($assigneeId) {
                // Create approval task
                $this->createApprovalTask($stepId, $step, $assigneeId, $data);

                // Set timeout if specified
                if (isset($step['timeout_hours'])) {
                    $this->scheduleStepTimeout($stepId, $step['timeout_hours']);
                }
            }

        } catch (Exception $e) {
            Logger::getInstance()->error("Approval step execution failed: $stepId", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Execute action step
     */
    private function executeActionStep($stepId, $step, $data) {
        try {
            $action = $step['action'];

            switch ($action) {
                case 'schedule_payment':
                    $this->schedulePayment($step, $data);
                    break;
                case 'create_task':
                    $this->createTask($step, $data);
                    break;
                case 'send_portal_invite':
                    $this->sendPortalInvite($step, $data);
                    break;
                case 'require_approval':
                    $this->requireApproval($step, $data);
                    break;
            }

            // Mark step as completed
            $this->updateStepStatus($stepId, 'completed');

        } catch (Exception $e) {
            Logger::getInstance()->error("Action step execution failed: $stepId", [
                'action' => $action,
                'error' => $e->getMessage()
            ]);
        }
    }



    /**
     * Schedule delayed step execution
     */
    private function scheduleDelayedStep($stepId, $step, $data) {
        try {
            $delayDays = $step['delay_days'] ?? 0;
            $executeAt = date('Y-m-d H:i:s', strtotime("+$delayDays days"));

            $stmt = $this->db->prepare("
                UPDATE workflow_steps SET scheduled_at = ?, status = 'scheduled'
                WHERE id = ?
            ");
            $stmt->execute([$executeAt, $stepId]);

        } catch (Exception $e) {
            Logger::getInstance()->error("Failed to schedule delayed step: $stepId", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Find assignee by role
     */
    private function findAssigneeByRole($role, $data) {
        try {
            // First try to find user with specific role
            $stmt = $this->db->prepare("
                SELECT u.id FROM users u
                INNER JOIN user_roles ur ON u.id = ur.user_id
                INNER JOIN roles r ON ur.role_id = r.id
                WHERE r.name = ? AND u.status = 'active'
                ORDER BY u.last_login DESC
                LIMIT 1
            ");
            $stmt->execute([$role]);
            $result = $stmt->fetch();

            if ($result) {
                return $result['id'];
            }

            // Fallback: find any active user with the role
            $stmt = $this->db->prepare("
                SELECT u.id FROM users u
                WHERE u.role = ? AND u.status = 'active'
                ORDER BY u.last_login DESC
                LIMIT 1
            ");
            $stmt->execute([$role]);
            $result = $stmt->fetch();

            return $result ? $result['id'] : null;

        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Create approval task
     */
    private function createApprovalTask($stepId, $step, $assigneeId, $data) {
        try {
            $taskTitle = "Approval Required: " . $step['name'];
            $taskDescription = "Please review and approve: " . json_encode($data);

            $stmt = $this->db->prepare("
                INSERT INTO tasks (title, description, priority, status, assigned_to, created_by, category, created_at)
                VALUES (?, ?, 'high', 'pending', ?, 1, 'approval', NOW())
            ");
            $stmt->execute([$taskTitle, $taskDescription, $assigneeId]);

            $taskId = $this->db->lastInsertId();

            // Link task to workflow step
            $stmt = $this->db->prepare("
                UPDATE workflow_steps SET related_task_id = ? WHERE id = ?
            ");
            $stmt->execute([$taskId, $stepId]);

        } catch (Exception $e) {
            Logger::getInstance()->error("Failed to create approval task: $stepId", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Schedule payment
     */
    private function schedulePayment($step, $data) {
        // Implementation for scheduling payments
        Logger::getInstance()->info("Payment scheduled", ['step' => $step, 'data' => $data]);
    }

    /**
     * Create task
     */
    private function createTask($step, $data) {
        try {
            $assigneeId = $this->findAssigneeByRole($step['assignee_role'], $data);

            $stmt = $this->db->prepare("
                INSERT INTO tasks (title, description, priority, status, assigned_to, created_by, category, created_at)
                VALUES (?, ?, ?, 'pending', ?, 1, 'workflow', NOW())
            ");
            $stmt->execute([
                $step['task_title'],
                json_encode($data),
                $step['task_priority'] ?? 'medium',
                $assigneeId
            ]);

        } catch (Exception $e) {
            Logger::getInstance()->error("Failed to create task", [
                'step' => $step,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send portal invite
     */
    private function sendPortalInvite($step, $data) {
        // Implementation for sending portal invites
        Logger::getInstance()->info("Portal invite sent", ['step' => $step, 'data' => $data]);
    }

    /**
     * Require approval
     */
    private function requireApproval($step, $data) {
        // Implementation for requiring approval
        Logger::getInstance()->info("Approval required", ['step' => $step, 'data' => $data]);
    }



    /**
     * Update step status
     */
    private function updateStepStatus($stepId, $status) {
        try {
            $stmt = $this->db->prepare("
                UPDATE workflow_steps SET status = ?, completed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $stepId]);

        } catch (Exception $e) {
            Logger::getInstance()->error("Failed to update step status: $stepId", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Schedule step timeout
     */
    private function scheduleStepTimeout($stepId, $hours) {
        try {
            $timeoutAt = date('Y-m-d H:i:s', strtotime("+$hours hours"));

            $stmt = $this->db->prepare("
                UPDATE workflow_steps SET timeout_at = ? WHERE id = ?
            ");
            $stmt->execute([$timeoutAt, $stepId]);

        } catch (Exception $e) {
            Logger::getInstance()->error("Failed to schedule step timeout: $stepId", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Process scheduled workflow steps
     */
    public function processScheduledSteps() {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM workflow_steps
                WHERE status = 'scheduled' AND scheduled_at <= NOW()
                ORDER BY scheduled_at ASC
            ");
            $stmt->execute();
            $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($steps as $step) {
                $this->executeScheduledStep($step);
            }

        } catch (Exception $e) {
            Logger::getInstance()->error("Failed to process scheduled steps", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Process timed out workflow steps
     */
    public function processTimedOutSteps() {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM workflow_steps
                WHERE status = 'pending' AND timeout_at <= NOW()
                ORDER BY timeout_at ASC
            ");
            $stmt->execute();
            $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($steps as $step) {
                $this->handleStepTimeout($step);
            }

        } catch (Exception $e) {
            Logger::getInstance()->error("Failed to process timed out steps", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Execute scheduled step
     */
    private function executeScheduledStep($step) {
        try {
            // Get workflow instance and step configuration
            $stmt = $this->db->prepare("
                SELECT wi.workflow_id, wi.trigger_data, w.definition
                FROM workflow_steps ws
                INNER JOIN workflow_instances wi ON ws.instance_id = wi.id
                INNER JOIN workflows w ON wi.workflow_id = w.id
                WHERE ws.id = ?
            ");
            $stmt->execute([$step['id']]);
            $workflowData = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($workflowData) {
                $definition = json_decode($workflowData['definition'], true);
                $triggerData = json_decode($workflowData['trigger_data'], true);

                $stepConfig = $definition['steps'][$step['step_index']];
                $this->executeWorkflowStep($step['instance_id'], $step['step_index'], $stepConfig, $triggerData);
            }

        } catch (Exception $e) {
            Logger::getInstance()->error("Failed to execute scheduled step: {$step['id']}", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle step timeout
     */
    private function handleStepTimeout($step) {
        try {
            // Mark step as timed out
            $this->updateStepStatus($step['id'], 'timed_out');

            // Create escalation task
            $stmt = $this->db->prepare("
                INSERT INTO tasks (title, description, priority, status, assigned_to, created_by, category, created_at)
                VALUES (?, ?, 'urgent', 'pending', 1, 1, 'escalation', NOW())
            ");
            $stmt->execute([
                'Workflow Step Timed Out',
                "Workflow step '{$step['step_name']}' has timed out and requires attention.",
                'urgent'
            ]);

            Logger::getInstance()->warning("Workflow step timed out", [
                'step_id' => $step['id'],
                'step_name' => $step['step_name']
            ]);

        } catch (Exception $e) {
            Logger::getInstance()->error("Failed to handle step timeout: {$step['id']}", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get workflow statistics
     */
    public function getWorkflowStats() {
        try {
            $stmt = $this->db->query("
                SELECT
                    COUNT(DISTINCT wi.id) as total_instances,
                    COUNT(CASE WHEN wi.status = 'running' THEN 1 END) as running_instances,
                    COUNT(CASE WHEN wi.status = 'completed' THEN 1 END) as completed_instances,
                    COUNT(CASE WHEN wi.status = 'failed' THEN 1 END) as failed_instances,
                    COUNT(ws.id) as total_steps,
                    COUNT(CASE WHEN ws.status = 'completed' THEN 1 END) as completed_steps,
                    COUNT(CASE WHEN ws.status = 'pending' THEN 1 END) as pending_steps,
                    COUNT(CASE WHEN ws.status = 'timed_out' THEN 1 END) as timed_out_steps
                FROM workflow_instances wi
                LEFT JOIN workflow_steps ws ON wi.id = ws.instance_id
            ");
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return [
                'total_instances' => 0,
                'running_instances' => 0,
                'completed_instances' => 0,
                'failed_instances' => 0,
                'total_steps' => 0,
                'completed_steps' => 0,
                'pending_steps' => 0,
                'timed_out_steps' => 0
            ];
        }
    }

    /**
     * Get available workflows
     */
    public function getAvailableWorkflows() {
        return $this->workflows;
    }

    /**
     * Get workflow instances
     */
    public function getWorkflowInstances($limit = 50, $offset = 0) {
        try {
            $stmt = $this->db->prepare("
                SELECT wi.*, w.name as workflow_name,
                       COUNT(ws.id) as total_steps,
                       COUNT(CASE WHEN ws.status = 'completed' THEN 1 END) as completed_steps
                FROM workflow_instances wi
                INNER JOIN workflows w ON wi.workflow_id = w.id
                LEFT JOIN workflow_steps ws ON wi.id = ws.instance_id
                GROUP BY wi.id
                ORDER BY wi.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            return [];
        }
    }
}
?>
?>
                       COUNT(CASE WHEN ws.status = 'completed' THEN 1 END) as completed_steps
        }
    }
?>
?>
                       COUNT(CASE WHEN ws.status = 'completed' THEN 1 END) as completed_steps
