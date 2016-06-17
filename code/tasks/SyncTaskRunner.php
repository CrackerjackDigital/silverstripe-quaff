<?php

class QuaffSyncTaskRunner extends TaskRunner {

	/**
	 * Overload to allow TaskName=all to run all tasks
	 * @param SS_HTTPRequest $request
	 */
	public function runTask($request) {
		$name = $request->param('TaskName');
		$tasks = $this->getTasks();

		$title = function ($content) {
			printf(Director::is_cli() ? "%s\n\n" : '<h1>%s</h1>', $content);
		};

		$message = function ($content) {
			printf(Director::is_cli() ? "%s\n" : '<p>%s</p>', $content);
		};

		foreach ($tasks as $task) {
			if ('all' == $name || $task['segment'] == $name) {
				$inst = Injector::inst()->create($task['class']);
				$title(sprintf('Running Task %s', $inst->getTitle()));

				if (!$inst->isEnabled()) {
					$message('The task is disabled');
					return;
				}

				$inst->run($request);
				return;
			}
		}

		$message(sprintf('The build task "%s" could not be found', Convert::raw2xml($name)));
	}
	/**
	 * @return array Array of associative arrays for each task (Keys: 'class', 'title', 'description')
	 */
	protected function getTasks() {
		$availableTasks = array();

		$taskClasses = ClassInfo::subclassesFor('QuaffSyncTask');
		// remove the base class
		array_shift($taskClasses);

		if($taskClasses) {
			$reorder = array();

			/** @var QuaffSyncTask $class */
			foreach ($taskClasses as $class) {
				$reorder[singleton($class)->sequence($reorder)] = $class;
			}
			ksort($reorder);

			foreach($reorder as $class) {
				if(!singleton($class)->isEnabled()) {
					continue;
				}

				$desc = (Director::is_cli())
					? Convert::html2raw(singleton($class)->getDescription())
					: singleton($class)->getDescription();

				$availableTasks[] = array(
					'class' => $class,
					'title' => singleton($class)->getTitle(),
					'segment' => str_replace('\\', '-', $class),
					'description' => $desc,
				);
			}
		}

		return $availableTasks;
	}
}