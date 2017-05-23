<?php
/**
 * ивент изменения статуса ревью
 * закрытие/открытие ревью
 *
 * действия: проверить что это ивент закрытия ревью, проверить что ревью по нашей задаче,
 * получить ID задачи джиры,
 * получить ID перехода в нужный статус, перейти в статус
 */
$projectId = $upsourceData['projectId'];
$reviewId = $upsourceData['data']['base']['reviewId'];
$newState = $upsourceData['data']['newState']; // open = 0, closed = 1

$logger->info(sprintf('Review %s/%s State updated to: %s', $projectId, $reviewId, $newState ? 'CLOSED' : 'OPENED'));

if ($newState === 1) {

    $reviewTitle = $upsourceApi->getReviewTitle($projectId, $reviewId);

    $logger->info(sprintf('Review title: %s', $reviewTitle));

    $issueKey = $upsourceApi->getIssueKeyByTitle($reviewTitle);

    if ($issueKey) {

        $logger->info(sprintf('Matched jira issue: %s', $issueKey));

        try {
            $trId = $jiraApi->findTransitionIdByName($issueKey, $jiraApi->getConfiguration()->getTransitionNameForReviewOk());
            $transition = new \JiraRestApi\Issue\Transition();
            $transition->setTransitionId($trId);
            $jiraApi->transition($issueKey, $transition);

            $logger->info('issue updated ok!');
            $logger->info('----------------------------------');

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