<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Hunter\Core\App\Application;
use Hunter\Core\Utility\StringConverter;

/**
 * 创建模块命令
 * php hunter module:create
 */
class ModuleCreateCmd extends BaseCommand {

   /** @var module list  */
   protected $moduleList;

   /**
   * @var StringConverter
   */
  protected $stringConverter;

   /**
    * InstallCommand constructor.
    * @param Site $site
    */
   public function __construct() {
       $application = new Application();
       $modulefiles = file_scan(HUNTER_ROOT.'/module', '/.*(\w+).*\.module/is', array('fullpath'=>true,'minDepth'=>2));
       $this->moduleList = $application->getModulesParameter($modulefiles);

       $this->stringConverter = new StringConverter();

       parent::__construct();
   }

   /**
    * {@inheritdoc}
    */
   protected function configure() {
       $this
           ->setName('module:create')
           ->setDescription('commands.module.create.description')
           ->addOption(
                'module',
                '',
                InputOption::VALUE_REQUIRED,
                'commands.generate.module.options.module'
            )
            ->addOption(
                'machine-name',
                '',
                InputOption::VALUE_REQUIRED,
                'commands.generate.module.options.machine-name'
            )
            ->addOption(
                'module-path',
                '',
                InputOption::VALUE_REQUIRED,
                'commands.generate.module.options.module-path'
            )
            ->addOption(
                'description',
                '',
                InputOption::VALUE_OPTIONAL,
                'commands.generate.module.options.description'
            )
            ->addOption(
                'core',
                '',
                InputOption::VALUE_OPTIONAL,
                'commands.generate.module.options.core'
            )
            ->addOption(
                'package',
                '',
                InputOption::VALUE_OPTIONAL,
                'commands.generate.module.options.package'
            )
            ->addOption(
                'module-file',
                '',
                InputOption::VALUE_NONE,
                'commands.generate.module.options.module-file'
            );
   }

   /**
    * {@inheritdoc}
    */
   protected function execute(InputInterface $input, OutputInterface $output) {
       $module = $input->getOption('module');
       $dir = HUNTER_ROOT . $input->getOption('module-path');
       $machineName = $input->getOption('machine-name');
       $description = $input->getOption('description');
       $core = $input->getOption('core');
       $package = $input->getOption('package');
       $moduleFile = $input->getOption('module-file');

       $dir .= '/'.$machineName;
       if (file_exists($dir)) {
           if (!is_dir($dir)) {
               throw new \RuntimeException(
                   sprintf(
                       'Unable to generate the module as the target directory "%s" exists but is a file.',
                       realpath($dir)
                   )
               );
           }
           $files = scandir($dir);
           if ($files != array('.', '..')) {
               throw new \RuntimeException(
                   sprintf(
                       'Unable to generate the module as the target directory "%s" is not empty.',
                       realpath($dir)
                   )
               );
           }
           if (!is_writable($dir)) {
               throw new \RuntimeException(
                   sprintf(
                       'Unable to generate the module as the target directory "%s" is not writable.',
                       realpath($dir)
                   )
               );
           }
       }

       $parameters = array(
         'module' => $module,
         'machine_name' => $machineName,
         'type' => 'module',
         'core' => $core,
         'description' => $description,
         'package' => $package,
       );

       $writed = $this->renderFile(
                     '/info.yml.html',
                     $dir.'/'.$machineName.'.info.yml',
                     $parameters
                 );

       if ($moduleFile) {
            $writed = $this->renderFile(
                         '/module.html',
                         $dir . '/' . $machineName . '.module',
                         $parameters
                      );
       }

       if($writed){
         $output->writeln('['.date("Y-m-d H:i:s").'] '.$input->getOption('module').' module create successful!');
       }else{
         $output->writeln('['.date("Y-m-d H:i:s").'] '.$input->getOption('module').' module create failed!');
       }
   }

   /**
    * {@inheritdoc}
    */
   protected function interact(InputInterface $input, OutputInterface $output) {
       $helper = $this->getHelper('question');

       // --module option
       $module = $input->getOption('module');
       if (!$module) {
           $question = new Question('Enter the new modue name:', '');
           $module = $helper->ask($input, $output, $question);
           $input->setOption('module', $module);
       }

       // --machine name option
       $machineName = $input->getOption('machine-name');
       if (!$machineName) {
           $default_machine_name = $this->stringConverter->createMachineName($module);
           $question = new Question('Enter the module machine name ['.$default_machine_name.']:', $default_machine_name);
           $machineName = $helper->ask($input, $output, $question);
           $input->setOption('machine-name', $machineName);
       }

       // --module path option
       $modulePath = $input->getOption('module-path');
       if (!$modulePath) {
           $question = new Question('Enter the modue path [/module]:', '/module');
           $modulePath = $helper->ask($input, $output, $question);
           $input->setOption('module-path', $modulePath);
       }

       // --module description option
       $description = $input->getOption('description');
       if (!$description) {
           $question = new Question('Enter modue description [My custom module]:', 'My custom module');
           $description = $helper->ask($input, $output, $question);
           $input->setOption('description', $description);
       }

       // --module option
       $package = $input->getOption('package');
       if (!$package) {
           $question = new Question('Enter package name [Custom]:', 'Custom');
           $package = $helper->ask($input, $output, $question);
           $input->setOption('package', $package);
       }

       // --module core option
       $core = $input->getOption('core');
       if (!$core) {
           $question = new Question('Enter HunterPHP core version [1.x]:', '1.x');
           $core = $helper->ask($input, $output, $question);
           $input->setOption('core', $core);
       }

       // --module file option
       $moduleFile = $input->getOption('module-file');
       if (!$moduleFile) {
           $question = new Question('Do you want to generate a .module file (yes/no) [yes]:', 'yes');
           $moduleFile = $helper->ask($input, $output, $question);
           $input->setOption('module-file', $moduleFile);
       }
   }

   /**
    * @param string $template
    * @param string $target
    * @param array  $parameters
    * @param null   $flag
    *
    * @return bool
    */
   protected function renderFile($template, $target, $parameters, $flag = null) {
       if (!is_dir(dirname($target))) {
           mkdir(dirname($target), 0777, true);
       }

       if (file_put_contents($target, theme('command')->render($template, $parameters), $flag)) {
           $this->files[] = str_replace(HUNTER_ROOT.'/', '', $target);

           return true;
       }

       return false;
   }

}
