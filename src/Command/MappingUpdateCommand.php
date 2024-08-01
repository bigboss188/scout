<?php

declare(strict_types=1);
/**
 * This file is part of hyperf-ext/scout.
 *
 * @link     https://github.com/hyperf-ext/scout
 * @contact  eric@zhu.email
 * @license  https://github.com/hyperf-ext/scout/blob/master/LICENSE
 */

namespace HyperfExt\Scout\Command;

use Hyperf\Collection\Arr;
use Hyperf\Context\ApplicationContext;
use HyperfExt\Scout\Command\Concerns\RequiresModelArgument;
use HyperfExt\Scout\Engine;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class MappingUpdateCommand extends AbstractCommand
{
    use RequiresModelArgument;

    public function __construct()
    {
        parent::__construct('scout:mapping:update');
        $this->setDescription('Update an Elasticsearch index mappings based on the given model');
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function handle(): void
    {
        $indices = ApplicationContext::getContainer()->get(Engine::class)->getClient()->indices();
        $model = $this->getModel();
        $indexName = $model->searchableAs();
        $mapping = $model->getScoutMapping();

        if (empty($mapping)) {
            throw new \LogicException('Nothing to update: the mapping is not specified.');
        }

        $params = [
            'index' => $indexName,
        ];

        if (200 != $indices->exists($params)->getStatusCode()) {
            throw new \LogicException(sprintf(
                'The index %s does not exist',
                $indexName
            ));
        }

        $params = Arr::add($params, 'body', $mapping);

        $indices->putMapping($params);

        $this->info(sprintf(
            'The %s mapping was updated.',
            $indexName
        ));
    }
}
