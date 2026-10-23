<?php
return [
    'policy_mode' => 'demo',
    'transitions' => [
        'Pending' => ['For Clarification', 'Assigned'],
        'For Clarification' => ['Pending'],
        'Assigned' => ['In Progress', 'On Hold', 'For Clarification'],
        'In Progress' => ['On Hold', 'Completed', 'For Clarification'],
        'On Hold' => ['In Progress', 'For Clarification'],
        'Completed' => [],
    ],
];
