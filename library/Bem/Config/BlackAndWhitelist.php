<?php

namespace Icinga\Module\Bem\Config;

use Icinga\Data\Filter\Filter;

class BlackAndWhitelist
{
    /** @var CellConfig */
    private $config;

    public function __construct(CellConfig $config)
    {
        $this->config = $config;
    }

    public function listFilterColumns()
    {
        return Filter::matchAny(
            $this->getBlacklistFilters()
        )->addFilter($this->getWhitelistFilter())->listFilteredColumns();
    }

    public function wants($object)
    {
        echo $this->getWhitelistFilter() . "\n";
        return $this->getWhitelistFilter()->matches($object)
            && ! $this->blacklists($object);
    }

    protected function blacklists($object)
    {
        foreach ($this->getBlacklistFilters() as $filter) {
            if ($filter->matches($object)) {
                return true;
            }
        }

        return false;
    }

    protected function getRejectFilterColumns()
    {
        return Filter::matchAny($this->getBlacklistFilters())->listFilteredColumns();
    }

    /**
     * @return Filter[]
     */
    protected function getBlacklistFilters()
    {
        $filters = [];
        foreach ($this->config->getSection('blacklist') as $key => $filter) {
            $filters[] = Filter::fromQueryString($filter);
        }

        return $filters;
    }

    /**
     * @return Filter
     */
    protected function getWhitelistFilter()
    {
        $filters = [];
        foreach ($this->config->getSection('whitelist') as $key => $filter) {
            $filters[] = Filter::fromQueryString($filter);
        }

        return Filter::matchAny($filters);
    }
}
