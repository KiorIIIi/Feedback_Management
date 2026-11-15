<?php
return [
    'policy_mode' => 'demo',
    // Known limitation pending product policy: only the requester can leave 'For Clarification'
    // (via reply) and there is no cancelled/withdrawn state, so an abandoned clarification stays open.
    'transitions' => [
        'Pending' => ['For Clarification', 'Assigned'],
        'For Clarification' => ['Pending'],
        'Assigned' => ['In Progress', 'On Hold', 'For Clarification'],
        'In Progress' => ['On Hold', 'Completed', 'For Clarification'],
        'On Hold' => ['In Progress', 'For Clarification'],
        'Completed' => [],
    ],
];
