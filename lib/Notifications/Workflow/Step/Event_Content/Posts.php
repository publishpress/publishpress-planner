<?php
namespace PublishPress\Notifications\Workflow\Step\Event_Content;

class Posts extends Base
{
    const META_KEY_SELECTED = '_psppno_evtcontposts';
    const META_VALUE_SELECTED = 'posts';

    public function __construct()
    {
        $this->name = 'posts';
        $this->label = __('Specific posts or pages', 'publishpress');
        parent::__construct();
    }

    public function filter_running_workflow_query_args($query_args, $event_args)
    {
        foreach ($this->get_filters() as $filter) {
            $query_args = $filter->get_run_workflow_query_args($query_args, $event_args);
        }
        return $query_args;
    }

    protected function get_filters($filters = [])
    {
        if (empty($this->cache_filters)) {
            $this->cache_filters = [new Filter\Posts($this->attr_prefix . '_' . $this->name)];
        }
        return $this->cache_filters;
    }
}
