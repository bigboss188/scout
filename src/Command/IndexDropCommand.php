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

use Hyperf\Context\ApplicationContext;
use HyperfExt\Scout\Command\Concerns\RequiresModelArgument;
use HyperfExt\Scout\Engine;

class IndexDropCommand extends AbstractCommand
{
    use RequiresModelArgument;

    public function __construct()
    {
        parent::__construct('scout:index:drop');
        $this->setDescription('Drop an Elasticsearch index based on the given model');
    }

    public function handle(): void
    {
        $indices = ApplicationContext::getContainer()->get(Engine::class)->getClient()->indices();
        $indexName = $this->getModel()->searchableAs();

        $params = [
            'index' => $indexName,
        ];

        if (200 != $indices->exists($params)->getStatusCode()) {
            throw new \LogicException(sprintf(
                'The index %s does not exist',
                $indexName
            ));
        }

        $indices->delete($params);

        $this->info(sprintf(
            'The index %s was deleted.',
            $indexName
        ));
    }
}
