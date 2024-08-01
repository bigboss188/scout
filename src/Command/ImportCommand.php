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
use HyperfExt\Scout\Event\ModelsImported;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventDispatcher\ListenerProviderInterface;

class ImportCommand extends AbstractCommand
{
    use RequiresModelArgument;

    public function __construct()
    {
        parent::__construct('scout:import');
        $this->setDescription('Import all of the given model\'s records into the elasticsearch index');
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function handle(): void
    {
        $model = $this->getModel();
        $class = get_class($model);

        ApplicationContext::getContainer()
            ->get(ListenerProviderInterface::class)
            ->on(ModelsImported::class, function ($event) use ($class) {
                $key = $event->models->last()->getScoutKey();
                $this->line('<comment>Imported [' . $class . '] models up to ID:</comment> ' . $key);
            });

        $model::makeAllSearchable();

        $this->info('All [' . $class . '] records have been imported.');
    }
}
