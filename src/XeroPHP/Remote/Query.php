<?php

namespace XeroPHP\Remote;

use XeroPHP\Application;
use DateTime;

class Query {

    const ORDER_ASC  = 'ASC';
    const ORDER_DESC = 'DESC';

    /** @var  \XeroPHP\Application */
    private $app;

    private $from_class;
    private $where;
    private $order;
    private $modifiedAfter;
    private $page;
    private $fromDate;
    private $toDate;
    private $date;
    private $standardLayout;
    private $offset;

    public function __construct(Application $app) {
        $this->app = $app;
        $this->where = array();
        $this->order = null;
        $this->modifiedAfter = null;
        $this->page = null;
        $this->offset = null;
    }

    /**
     * @param string $class
     * @return $this
     */
    public function from($class) {

        $this->from_class = $this->app->validateModelClass($class);

        return $this;
    }

    /**
     * @return $this
     */
    public function where() {
        $args = func_get_args();

        if(func_num_args() === 2) {
            if(is_bool($args[1])) {
                $this->where[] = sprintf('%s=%s', $args[0], $args[1] ? 'true' : 'false');
            } elseif(is_int($args[1])) {
                $this->where[] = sprintf('%s==%s', $args[0], $args[1]);
            } elseif(preg_match('/^(\'|")?(true|false)("|\')?$/i', $args[1])) {
                $this->where[] = sprintf('%s=%s', $args[0], $args[1]);
            } elseif(preg_match('/^([a-z]+)\.\1ID$/i', $args[0]) && preg_match('/^[0-9a-f]{8}-([0-9a-f]{4}-){3}[0-9a-f]{12}$/i', $args[1])) {
                $this->where[] = sprintf('%s=Guid("%s")', $args[0], $args[1]);
            } else {
                $this->where[] = sprintf('%s=="%s"', $args[0], $args[1]);
            }
        } else {
            $this->where[] = $args[0];
        }

        return $this;
    }

    public function getWhere() {
        return implode(' AND ', $this->where);
    }

    /**
     * @param string $order
     * @param string $direction
     * @return $this
     */
    public function orderBy($order, $direction = self::ORDER_ASC) {
        $this->order = sprintf('%s %s', $order, $direction);

        return $this;
    }

    /**
     * @param \DateTimeInterface|null $modifiedAfter
     * @return $this
     */
    public function modifiedAfter(\DateTimeInterface $modifiedAfter = null) {
        if($modifiedAfter === null) {
            $modifiedAfter = new \DateTime('@0'); // since ever
        }

        $this->modifiedAfter = $modifiedAfter->format('c');

        return $this;
    }

    /**
     * @param DateTime $fromDate
     * @return $this
     */
    public function fromDate(DateTime $fromDate) {
        $this->fromDate = $fromDate->format('Y-m-d');
        return $this;
    }

    /**
     * @param DateTime $toDate
     * @return $this
     */
    public function toDate(DateTime $toDate) {
        $this->toDate = $toDate->format('Y-m-d');
        return $this;
    }

    /**
     * @param DateTime $date
     * @return $this
     */
    public function date(DateTime $date) {
        $this->date = $date->format('Y-m-d');
        return $this;
    }

    /**
     * @param string $standardLayout
     * @return $this
     */
    public function standardLayout($standardLayout = 'true') {
        $this->standardLayout = $standardLayout;
        return $this;
    }

    /**
     * @param int $page
     * @return $this
     * @throws Exception
     */
    public function page($page = 1) {
        /** @var ObjectInterface $from_class */
        $from_class = $this->from_class;
        if(!$from_class::isPageable()){
            throw new Exception(sprintf('%s does not support paging.', $from_class));
        }

        $this->page = intval($page);

        return $this;
    }

    /**
     * @param int $offset
     * @return $this
     */
    public function offset($offset = 0) {
        $this->offset = intval($offset);

        return $this;
    }

    /**
     * @return Collection
     */
    public function execute() {

        /** @var ObjectInterface $from_class */
        $from_class = $this->from_class;
        $url = new URL($this->app, $from_class::getResourceURI(), $from_class::getAPIStem());
        $request = new Request($this->app, $url, Request::METHOD_GET);

        $where = $this->getWhere();
        if(!empty($where)) {
            $request->setParameter('where', $where);
        }

        if($this->order !== null) {
            $request->setParameter('order', $this->order);
        }

        if($this->modifiedAfter !== null) {
            $request->setHeader('If-Modified-Since', $this->modifiedAfter);
        }

        if($this->fromDate !== null) {
            $request->setParameter('fromDate', $this->fromDate);
        }

        if($this->toDate !== null) {
            $request->setParameter('toDate', $this->toDate);
        }

        if($this->date !== null) {
            $request->setParameter('date', $this->date);
        }

        if($this->page !== null) {
            $request->setParameter('page', $this->page);
        }

        if($this->offset !== null) {
            $request->setParameter('offset', $this->offset);
        }

        if($this->standardLayout !== null) {
            $request->setParameter('standardLayout', $this->standardLayout);
        }

        $request->send();

        $elements = new Collection();
        foreach($request->getResponse()->getElements() as $element) {
            /** @var Object $built_element */
            $built_element = new $from_class($this->app);
            $built_element->fromStringArray($element);
            $elements->append($built_element);
        }

        return $elements;
    }

    /**
     * @return mixed
     */
    public function getFrom() {
        return $this->from_class;
    }
}
