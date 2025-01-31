<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class commanderCommand extends Command
{
    public $foldersDoNotClear = array('treewarm','PSG-param','imagecache','templates');

    protected function configure()
    {
        $this->setName('commander:clearCache')
            ->setDescription('Clears all the cache')
            ->setHelp('This command allows you remove cache')
			->addOption(
				'PSG',
				null,
				InputOption::VALUE_OPTIONAL,
				'Remove PSG cache',
				false 
		);
			
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(['Begin cache clear', '========================', '',]);
         $cachePath=xConfig::get('PATH', 'CACHE');
        $folders = array_diff(scandir($cachePath), array('.', '..'));

		 
		$optionValue = $input->getOption('PSG');
		
		if($optionValue !== false){		
			 $this->foldersDoNotClear = array_diff($this->foldersDoNotClear, ['PSG-param']);			
		}
		
		
        if (!empty($folders)) {
            foreach($folders as $path)
            {

                if(!in_array($path,$this->foldersDoNotClear)) {
                    
					$output->writeln([$cachePath.$path]);
					
                    if (strstr(php_uname(),'Windows')) {
                        exec(sprintf("rd /s /q %s", escapeshellarg($cachePath.$path)));

                    } else {
												
                        exec(sprintf("rm -rf %s", escapeshellarg($cachePath.$path)));
                    }
                }

            }
        }
		
		exec("chmod -R 777 ".$cachePath);
		
        if (strstr(php_uname(),'Windows')) {
            $output->writeln(['Remove bak files', '========================', '',]);
            exec(escapeshellarg('del /s /f /q '.$_SERVER['DOCUMENT_ROOT'].'\*.bak'));
        }
		
		
		
        X4\Classes\XRegistry::get('EVM')->fire('AdminPanel:afterCacheClear',array('instance'=>$this));
		
		$output->writeln(['','========================', 'set permissions']);
		exec("chmod -R 777 ".$cachePath);
        $output->writeln(['','========================', 'Cleared']);
    }
}



