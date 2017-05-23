<?php
/**
 * ивент изменения статуса ревьюера
 * например - апрув ревью
 *
 * действия: проверить что это ивент апрува ревью, проверить что ревью по нашей задаче,
 * получить ID задачи джиры, проверить что все ревьюеры апрувнули,
 * получить ID перехода в нужный статус, перейти в статус
 */
$projectId = $upsourceData['projectId'];
$reviewId = $upsourceData['data']['base']['reviewId'];
$byWho = $upsourceData['data']['participant']['userName'];
$newState = $upsourceData['data']['newState']; // Unread = 0, Read = 1, Accepted = 2, Rejected = 3

$stateMap = [
    'Unread',
    'Read',
    'Accepted',
    'Rejected',
];

$logger->info(sprintf('Review %s/%s participant updated to: %s, by: %s', $projectId, $reviewId, $stateMap[$newState], $byWho));

if ($newState === 2) {

    $reviewTitle = $upsourceApi->getReviewTitle($projectId, $reviewId);

    $logger->info(sprintf('Review title: %s', $reviewTitle));

    $issueKey = $upsourceApi->getIssueKeyByTitle($reviewTitle);

    if ($issueKey) {

        $logger->info(sprintf('Matched jira issue: %s', $issueKey));

        try {

            if ($upsourceApi->isReviewAcceptedByEveryone($projectId, $reviewId)) {

                $trId = $jiraApi->findTransitionIdByName($issueKey, $jiraApi->getConfiguration()->getTransitionNameForReviewOk());
                $transition = new \JiraRestApi\Issue\Transition();
                $transition->setTransitionId($trId);
                $jiraApi->transition($issueKey, $transition);

                $logger->info('issue updated ok!');
                $logger->info('----------------------------------');
            } else {
                $logger->info('review not accepted by all participants!');
                $logger->info('----------------------------------');
            }
        } catch (TransitionNotFound $e) {
            $logger->info($e->getMessage());
            $logger->info('cant change issue status!');
            $logger->info('----------------------------------');
        } catch (\JiraRestApi\JiraException $e) {
            $logger->error($e->getMessage());
            $logger->error($e->getTraceAsString());
            $logger->error('----------------------------------');
        }
    } else {
        $logger->error('cant match jira issue!');
        $logger->error('----------------------------------');
    }
}