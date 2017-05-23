<?php
return [
    'upsourceHost' => 'http://upsource.yourhost.com',
    'upsourceUser' => 'ups-user',
    'upsourcePassword' => 'very-good-pwd',
    'reviewTitleIssueRegexp' => '#^(teamPrefix-\d+).+#',
    'gitBranchIssueRegexp' => '#^issue_(teamPrefix-\d+).*#',

    'jiraHost' => 'https://jira.yourhost.com',
    'jiraUser' => 'jira-rest-user',
    'jiraPassword' => 'very-good-pwd',
    'transitionNameForReviewOk' => 'Code Review Ok',
    'transitionNameForReviewStart' => 'On Code Review',
];