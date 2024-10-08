<?php

declare(strict_types=1);
/**
 * This file is part of hyperf-ext/scout.
 *
 * @link     https://github.com/hyperf-ext/scout
 * @contact  eric@zhu.email
 * @license  https://github.com/hyperf-ext/scout/blob/master/LICENSE
 */
namespace HyperfExt\Scout\Event;

use Hyperf\Database\Model\Collection;

class ModelsImported
{
    /**
     * The model collection.
     *
     * @var Collection
     */
    public Collection $models;

    /**
     * Create a new event instance.
     */
    public function __construct(Collection $models)
    {
        $this->models = $models;
    }
}
