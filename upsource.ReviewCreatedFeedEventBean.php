<?php
/**
 * ивент создания ревью
 *
 * действия: проверить что ревью по нашей задаче, получить ID задачи джиры,
 * получить ID перехода в нужный статус, перейти в статус, приложить в задачу джиры ссылку на ревью
 */
$projectId = $upsourceData['projectId'];
$reviewId = $upsourceData['data']['base']['reviewId'];

$logger->info(sprintf('Created Review: %s/%s', $projectId, $reviewId));

$reviewTitle = $upsourceApi->getReviewTitle($projectId, $reviewId);

$logger->info(sprintf('Review title: %s', $reviewTitle));

$issueKey = $upsourceApi->getIssueKeyByTitle($reviewTitle);

if ($issueKey) {

    $reviewUrl = $upsourceApi->getReviewUrl($projectId, $reviewId);

    $logger->info(sprintf('Matched jira issue: %s', $issueKey));

    try {
        $trId = $jiraApi->findTransitionIdByName($issueKey, $jiraApi->getConfiguration()->getTransitionNameForReviewStart());
        $transition = new \JiraRestApi\Issue\Transition();
        $transition->setTransitionId($trId);
        $jiraApi->transition($issueKey, $transition);

        $jiraApi->createRemoteLink($issueKey, $reviewUrl);

        $logger->info('issue updated ok!');
        $logger->info('----------------------------------');

    } catch (TransitionNotFound $e) {
        $logger->info($e->getMessage());
        $jiraApi->createRemoteLink($issueKey, $reviewUrl);
        $logger->info('attached review link to issue');
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