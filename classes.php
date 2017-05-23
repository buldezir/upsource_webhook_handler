<?php

use JiraRestApi\Issue\IssueService;

class TransitionNotFound extends \JiraRestApi\JiraException
{
}

class UpSourceConfiguration extends \JiraRestApi\Configuration\ArrayConfiguration
{
    /**
     * @var string
     */
    protected $upsourceHost;

    /**
     * @var string
     */
    protected $upsourceUser;

    /**
     * @var string
     */
    protected $upsourcePassword;

    /**
     * @var string
     */
    protected $reviewTitleIssueRegexp;

    /**
     * @var string
     */
    protected $gitBranchIssueRegexp;

    /**
     * @return string
     */
    public function getReviewTitleIssueRegexp(): string
    {
        return $this->reviewTitleIssueRegexp;
    }

    /**
     * @return string
     */
    public function getGitBranchIssueRegexp(): string
    {
        return $this->gitBranchIssueRegexp;
    }

    public function getJiraHost()
    {
        return $this->upsourceHost;
    }

    public function getJiraUser()
    {
        return $this->upsourceUser;
    }

    public function getJiraPassword()
    {
        return $this->upsourcePassword;
    }
}

class JiraConfiguration extends \JiraRestApi\Configuration\ArrayConfiguration
{
    /**
     * @var string
     */
    protected $transitionNameForReviewOk;

    /**
     * @var string
     */
    protected $transitionNameForReviewFail;

    /**
     * @var string
     */
    protected $transitionNameForReviewStart;

    /**
     * @return string
     */
    public function getTransitionNameForReviewOk(): string
    {
        return $this->transitionNameForReviewOk;
    }

    /**
     * @return string
     */
    public function getTransitionNameForReviewFail(): string
    {
        return $this->transitionNameForReviewFail;
    }

    /**
     * @return string
     */
    public function getTransitionNameForReviewStart(): string
    {
        return $this->transitionNameForReviewStart;
    }
}

/**
 * оказалось можно не писать свой клиент
 * Class UpSourceClient
 *
 * @method UpSourceConfiguration getConfiguration
 * @method string getReviewDetails($params)
 */
class UpSourceClient extends \JiraRestApi\JiraClient
{
    /**
     * в заголовке ревью может быть:
     * название ветки гита (если это ревью бранча)
     * комент к комиту (если это ревью коммита)
     * нужно вытащить оттуда номер задачи джиры
     * @param null|string $reviewTitle
     * @return null|string
     */
    public function getIssueKeyByTitle($reviewTitle)
    {
        if (preg_match($this->getConfiguration()->getReviewTitleIssueRegexp(), $reviewTitle, $matches)) {
            return $matches[1];
        } elseif (preg_match($this->getConfiguration()->getGitBranchIssueRegexp(), $reviewTitle, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * так как в данных, которые приходят из веб-хука, нет заголовка(названия) ревью
     * получаем его через api (потому что только в из него можно узнать номер задачи в джире)
     * @param string $projectId
     * @param string $reviewId
     * @return null|string
     */
    public function getReviewTitle(string $projectId, string $reviewId)
    {
        $json_result = $this->getReviewDetails(['projectId' => $projectId, 'reviewId' => $reviewId]);
        $result = json_decode($json_result, true);
        return $result['result']['title'] ?? null;
    }

    /**
     * сделать запрос к api и узать все ли ревьюеры апрувнули
     * @param string $projectId
     * @param string $reviewId
     * @return bool
     * @throws \JiraRestApi\JiraException
     */
    public function isReviewAcceptedByEveryone(string $projectId, string $reviewId): bool
    {
        $json_result = $this->getReviewDetails(['projectId' => $projectId, 'reviewId' => $reviewId]);
        $result = json_decode($json_result, true);
        if (isset($result['result']['completionRate'])) {
            return $result['result']['completionRate']['reviewersCount'] > 0 && $result['result']['completionRate']['completedCount'] === $result['result']['completionRate']['reviewersCount'];
        }
        throw new \JiraRestApi\JiraException('Cant find review completionRate');
    }

    /**
     * сформировать полный адрес ревью (например чтобы добавить в задачу джиры)
     * @param string $projectId
     * @param string $reviewId
     * @return string
     */
    public function getReviewUrl(string $projectId, string $reviewId): string
    {
        return sprintf('%s/%s/review/%s', $this->getConfiguration()->getJiraHost(), $projectId, $reviewId);
    }

    /**
     * переопределение метода для работы с api апсорса (вообще-то базовый класс называется JiraClient)
     * но так как, к счастью, json api стандартизировано - в остальном они работают одинаково
     * @param string $context
     * @return string
     */
    protected function createUrlByContext($context)
    {
        $host = $this->getConfiguration()->getJiraHost();
        return $host . '/~rpc/' . $context;
    }

    /**
     * магия вызывов методов api апсорса
     * @see http://upsource/~api_doc/reference/Service.html#messages.UpsourceRPC
     * @param string $method
     * @param array $arguments
     * @return string
     */
    public function __call(string $method, array $arguments)
    {
        $context = $method . '?params=' . urlencode(json_encode($arguments[0]));
        return $this->exec($context);
    }
}

/**
 * Class JiraClient
 *
 * @method JiraConfiguration getConfiguration
 */
class JiraClient extends IssueService
{
    /**
     * мы знаем только название нужного статуса, а нужно получить ID перехода в него
     * @param string $issueKey
     * @param string $name
     * @return string
     * @throws TransitionNotFound
     */
    public function findTransitionIdByName(string $issueKey, string $name)
    {
        $list = $this->getTransition($issueKey);
        foreach ($list as $item) {
            if ($item->name === $name) {
                return $item->id;
            }
        }
        throw new TransitionNotFound("Cant find ID for transition '{$name}'");
    }

    /**
     * добавить внешнюю ссылку в задачу
     * @param $issueIdOrKey
     * @param $url
     * @param null $title
     * @return string
     */
    public function createRemoteLink($issueIdOrKey, $url, $title = null)
    {
        $this->log->addInfo("createRemoteLink=\n");

        if ($title === null) {
            $title = $url;
        }

        $ar = [
            'object' => [
                'url' => $url,
                'title' => $title,
            ]
        ];

        $data = json_encode($ar);

        $ret = $this->exec("/issue/$issueIdOrKey/remotelink", $data, 'POST');

        $this->log->addInfo('create remote link ' . $issueIdOrKey . ' : ' . $url . ' result=' . var_export($ret, true));

        return $ret;
    }
}