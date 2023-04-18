<?php

namespace BookStack\Console\Commands;

use BookStack\Entities\Models\Bookshelf;
use BookStack\Entities\Repos\BookshelfRepo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AssignBookToProjectTeam extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'book:assign';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto assign book editor access to the project team';

    protected BookshelfRepo $shelfRepo;

    public function __construct(BookshelfRepo $shelfRepo)
    {
        $this->shelfRepo = $shelfRepo;

        parent::__construct();
    }


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            Bookshelf::whereNull('deleted_at')->orderBy('id','desc')->chunk(100, function ($shelves) {
                foreach ($shelves as $shelve) {
                    $this->shelfRepo->getBookShelfData($shelve);
                }
            });
            Log::channel('command')->info("Shelves have been assigned to the project team");
        }
        catch (\Exception $e) {
            Log::channel('command')->error("AutoAssignBook Command ".$e->getMessage());
        }
    }

}
