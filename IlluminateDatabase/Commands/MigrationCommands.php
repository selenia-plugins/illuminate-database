<?php
namespace Electro\Plugins\IlluminateDatabase\Commands;

use Electro\Core\Assembly\ModuleInfo;
use Electro\Core\ConsoleApplication\Lib\ModulesUtil;
use Electro\Core\ConsoleApplication\Services\ConsoleIO;
use Electro\Interfaces\Migrations\MigrationsInterface;
use Electro\Interop\MigrationStruct as Migration;
use Electro\Plugins\IlluminateDatabase\Config\MigrationsSettings;

/**
 * Database migration commands.
 */
class MigrationCommands
{
  /**
   * @var ConsoleIO
   */
  private $io;
  /**
   * @var MigrationsInterface
   */
  private $migrationsAPI;
  /**
   * @var ModulesUtil
   */
  private $modulesUtil;
  /**
   * @var MigrationsSettings
   */
  private $settings;

  function __construct (MigrationsSettings $settings, ConsoleIO $io, ModulesUtil $modulesUtil,
                        MigrationsInterface $migrationsAPI)
  {
    $this->io            = $io;
    $this->modulesUtil   = $modulesUtil;
    $this->settings      = $settings;
    $this->migrationsAPI = $migrationsAPI;
  }

  /**
   * Create a new database migration
   *
   * @param string $moduleName [optional] The target module (vendor-name/package-name syntax).
   *                           If not specified, the user will be prompted for it
   * @param string $name       [optional] The name of the migration (a human-friendly description, it may contain
   *                           spaces, but not accented characters). If not specified, the user will be prompted for it
   * @param array  $options
   * @option $class|l Use a class implementing "Phinx\Migration\CreationInterface" to generate the template
   * @option $template|t Use an alternative template
   * @option $no-doc|d Do not generate a documentation block.
   * @return int Status code
   */
  function makeMigration ($moduleName = null, $name = null, $options = [
    'class|l'    => null,
    'template|t' => null,
    'no-doc|d'   => false,
  ])
  {
    $this->setupModule ($moduleName);

    return 0;
  }

  /**
   * Create a new database seeder
   *
   * @param string $moduleName [optional] The target module (vendor-name/package-name syntax).
   *                           If not specified, the user will be prompted for it
   * @param string $name       [optional] The name of the seeder (a human-friendly description, it may contain
   *                           spaces, but not accented characters). If not specified, the user will be prompted for it
   * @return int Status code
   */
  function makeSeeder ($moduleName = null, $name = null)
  {
    $this->setupModule ($moduleName);

    return 0;
  }

  /**
   * Runs all pending migrations of a module, optionally up to a specific version
   *
   * @param string $moduleName [optional] The target module (vendor-name/package-name syntax).
   *                           If not specified, the user will be prompted for it
   * @param array  $options
   * @option $target|t The version number to migrate to
   * @return int Status code
   */
  function migrate ($moduleName = null, $options = [
    'target|t' => null,
  ])
  {
    $this->setupModule ($moduleName);
    $this->migrationsAPI->migrate ($options['target']);
    return 0;
  }

  /**
   * Reset and re-run all migrations
   *
   * @param string $moduleName [optional] The target module (vendor-name/package-name syntax).
   *                           If not specified, the user will be prompted for it
   * @param array  $options
   * @option $seed When specified, runs the seeder after the migration
   * @option $seeder|s The name of the seeder (in camel case)
   * @return int Status code
   */
  function migrateRefresh ($moduleName = null, $options = [
    '--seed'   => null,
    'seeder|s' => 'Seeder',
  ])
  {
    $this->setupModule ($moduleName);
    $r = $this->migrateRollback ($moduleName, ['target' => 0]);
    if ($r) return $r;
    $r = $this->migrate ($moduleName);
    if ($r) return $r;
    if ($options['seed'])
      return $this->migrateSeed ($moduleName, $options);
    return 0;
  }

  /**
   * Rollback all database migrations
   *
   * @param string $moduleName [optional] The target module (vendor-name/package-name syntax).
   *                           If not specified, the user will be prompted for it
   * @return int Status code
   */
  function migrateReset ($moduleName = null)
  {
    $this->setupModule ($moduleName);
    return $this->migrateRollback ($moduleName, ['target' => 0]);
  }

  /**
   * Reverts the last migration of a specific module, or optionally up to a specific version
   *
   * @param string $moduleName [optional] The target module (vendor-name/package-name syntax).
   *                           If not specified, the user will be prompted for it
   * @param array  $options
   * @option $target|t The version number to rollback to
   * @option $date|d   The date to rollback to
   * @return int Status code
   */
  function migrateRollback ($moduleName = null, $options = [
    'target|t' => null,
    'date|d'   => null,
  ])
  {
    $this->setupModule ($moduleName);
    $this->migrationsAPI->rollback ($options['target'], $options['date']);
    return 0;
  }

  /**
   * Run all available seeders of a specific module, or just a specific seeder
   *
   * @param string $moduleName [optional] The target module (vendor-name/package-name syntax).
   *                           If not specified, the user will be prompted for it
   * @param array  $options
   * @option $seeder|s The name of the seeder (in camel case)
   * @return int Status code
   */
  function migrateSeed ($moduleName = null, $options = [
    'seeder|s' => 'Seeder',
  ])
  {
    $this->setupModule ($moduleName);
    $this->migrationsAPI->seed ($options['seeder']);
    return 0;
  }

  /**
   * Print a list of all migrations of a specific module, along with their current status
   *
   * @param string $moduleName [optional] The target module (vendor-name/package-name syntax).
   *                           If not specified, the user will be prompted for it
   * @param array  $options
   * @option $format|f      The output format. Allowed values: 'json|text'. If not specified, text is output.
   * @return int Status code
   */
  function migrateStatus ($moduleName = null, $options = [
    'format|f' => 'text',
  ])
  {
    $this->setupModule ($moduleName);
    $migrations   = $this->migrationsAPI->status ();
    $formattedMig = map ($migrations, function ($mig) {
      return [
        Migration::toDateStr ($mig[Migration::date]),
        $mig[Migration::name],
        $mig[Migration::status],
      ];
    });
    switch ($options['format']) {
      case 'json':
        $this->io->writeln (json_encode (array_values ($migrations), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        break;
      case 'text':
        if ($formattedMig)
          $this->io->table (['Date', 'Name', 'Status'], $formattedMig, [20, 40, 8]);
        else $this->io->cancel ('The module has no migrations');
        break;
      default:
        $this->io->error ('Invalid format');
    }
    return 0;
  }

  /**
   * Prepares the migrations context for running on the specified module.
   *
   * It also validates the module name and/or asks for it, if empty. In the later case, the `$moduleName` argument will
   * be updated on the caller.
   *
   * @param string $moduleName vendor-name/package-name
   */
  private function setupModule (&$moduleName)
  {
    $this->modulesUtil->selectModule ($moduleName, function (ModuleInfo $module) { return $module->enabled; });
    $this->migrationsAPI->module ($moduleName);
  }

}