<?php

/**
 * 
 */
class NpmTestJestUnitTestEngine extends ArcanistUnitTestEngine {

  private $affectedTests;
  private $projectRoot;

  
  const JEST_PATH = 'npm test -- ';
  const TOO_MANY_FILES_TO_COVER = 100;
  const GIGANTIC_DIFF_THRESHOLD = 200;
  const MAX_EXECUTING_FUTURES = 2;    //max number of processes at one time - Make this higher if you got lots of processors?

  public function run() {

    $this->projectRoot = $this->getWorkingCopy()->getProjectRoot();

    $this->affectedTests = array();

    foreach ($this->getPaths() as $path) {


      $path = Filesystem::resolvePath($path, $this->projectRoot);

      if (is_dir($path)) {
        continue;
      }

      //Make sure we have a supported extension
      if( substr($path, -4) != '.tsx' && substr($path, -4) != '.jsx' && substr($path, -3) != '.ts' && substr($path, -3) !=  '.js' ) 
      {
        continue;
      }

      //Is the changed file, itself, a test?
      if( substr($path, -8) == 'test.tsx' || substr($path, -8) == 'test.jsx' || substr($path, -7) == 'test.ts' || substr($path, -7) == 'test.js' )
      {
        $this->affectedTests[$path] = $path;
        continue;
      }

      if ($test = $this->findTestFile($path)) {
        $this->affectedTests[$path] = $test;
      }

    }

    if (empty($this->affectedTests)) {
      throw new ArcanistNoEffectException(pht('No tests to run.'));
    }

    //At this point we have $this=>affectedTests which should contain a list of test files to execute

    $futures = array();
    
    $allTests = "";
 
    foreach ($this->affectedTests as $class_path => $test_path) {
      if (!Filesystem::pathExists($test_path)) {
        continue;
      }
      
      $allTests.=" ".$test_path;
    }

    $futures[$allTests] = new ExecFuture(
      '%C %C %C',
        self::JEST_PATH, 
        implode(' ',$this->getJestOptions($test_path)), 
        $allTests
    ); 


    $completed = array();
    $iterator = new FutureIterator($futures);

    $iterator->limit( self::MAX_EXECUTING_FUTURES );

    foreach ($iterator->setUpdateInterval(0.2) as $_) {
      foreach ($futures as $key => $future) {
        if (isset($completed[$key])) {
          continue;
        }
        if ($future->isReady()) {
          $completed[$key] = true;
        }
        break;
      }
    }

    $results = array();  

    foreach ($futures as $future) {
      $results[] = $this->getFutureResults($future);
    }

    if( count($results) == 0 )
	    return $results;      
    
    return call_user_func_array('array_merge', $results); //I honestly have no idea why the fuck this has to be here like this - It works though

  }

  
  /**
   * Search for test cases for a given file in a large number of "reasonable"
   * locations. See @{method:getSearchLocationsForTests} for specifics.
   *
   *
   * @param   string      file to locate test cases for.
   * @return  string|null Path to test cases, or null.
   */
  private function findTestFile($path) {
    $root = $this->projectRoot;
    $path = Filesystem::resolvePath($path, $root);

    $file = basename($path);
    $possible_files = array(
      $file,
      substr($file, 0, -4).'.test.jsx',
      substr($file, 0, -4).'.test.tsx',
      substr($file, 0, -3).'.test.js',
      substr($file, 0, -3).'.test.ts',
    );

    $search = self::getSearchLocationsForTests($path);

    foreach ($search as $search_path) {
      foreach ($possible_files as $possible_file) {
        $full_path = $search_path.$possible_file;
        if (!Filesystem::pathExists($full_path)) {
          // If the file doesn't exist, it's clearly a miss.
          continue;
        }
        if (!Filesystem::isDescendant($full_path, $root)) {
          // Don't look above the project root.
          continue;
        }
        if (0 == strcasecmp(Filesystem::resolvePath($full_path), $path)) {
          // Don't return the original file.
          continue;
        }
        return $full_path;
      }
    }

    return null;
  }


  /**
   * Get places to look for Unit tests that cover a given file. For some
   * file "/a/b/c/X.[ext]", we look in the same directory:
   *
   *  /a/b/c/
   *
   * We then look in all parent directories for a directory named "tests/"
   * (or "Tests/"):
   *
   *  /a/b/c/tests/
   *  /a/b/tests/
   *  /a/tests/
   *  /tests/
   *
   * We also try to replace each directory component with "tests/":
   *
   *  /a/b/tests/
   *  /a/tests/c/
   *  /tests/b/c/
   *
   * We also try to add "tests/" at each directory level:
   *
   *  /a/b/c/tests/
   *  /a/b/tests/c/
   *  /a/tests/b/c/
   *  /tests/a/b/c/
   *
   * This finds tests with a layout like:
   *
   *  docs/
   *  src/
   *  tests/
   *
   * ...or similar. This list will be further pruned by the caller; it is
   * intentionally filesystem-agnostic to be unit testable.
   *
   * @param   string        File to locate test cases for.
   * @return  list<string>  List of directories to search for tests in.
   */
  public static function getSearchLocationsForTests($path) {
    $file = basename($path);
    $dir  = dirname($path);

    $test_dir_names = array('tests', 'Tests');

    $try_directories = array();

    // Try in the current directory.
    $try_directories[] = array($dir);

    // Try in a tests/ directory anywhere in the ancestry.
    foreach (Filesystem::walkToRoot($dir) as $parent_dir) {
      if ($parent_dir == '/') {
        // We'll restore this later.
        $parent_dir = '';
      }
      foreach ($test_dir_names as $test_dir_name) {
        $try_directories[] = array($parent_dir, $test_dir_name);
      }
    }

    // Try replacing each directory component with 'tests/'.
    $parts = trim($dir, DIRECTORY_SEPARATOR);
    $parts = explode(DIRECTORY_SEPARATOR, $parts);
    foreach (array_reverse(array_keys($parts)) as $key) {
      foreach ($test_dir_names as $test_dir_name) {
        $try = $parts;
        $try[$key] = $test_dir_name;
        array_unshift($try, '');
        $try_directories[] = $try;
      }
    }

    // Try adding 'tests/' at each level.
    foreach (array_reverse(array_keys($parts)) as $key) {
      foreach ($test_dir_names as $test_dir_name) {
        $try = $parts;
        $try[$key] = $test_dir_name.DIRECTORY_SEPARATOR.$try[$key];
        array_unshift($try, '');
        $try_directories[] = $try;
      }
    }

    $results = array();
    foreach ($try_directories as $parts) {
      $results[implode(DIRECTORY_SEPARATOR, $parts).DIRECTORY_SEPARATOR] = true;
    }

    return array_keys($results);
  }
 
  private function getRoot() {
    return $this->getWorkingCopy()->getProjectRoot();
  }
 
 
  private function getFutureResults($future) {

    //Using resolveKill() because I can't find any method in ExecFuture that returns the shit I want
    $resultFuture = $future->resolveKill();
    //We have to cut the string at the JSON result that appeared from npm test     
    $jsonStart = strpos($resultFuture[1],"\n{");

    //Get the json string
    $jsonString = trim(substr($resultFuture[1],$jsonStart));

    $raw_results = null;
    
    //Decode the json string
    $raw_results = json_decode($jsonString,true);

    //Capture the tesResults output of the array - Have a look at T330#5882 for a sample output
    $raw_results = $raw_results["testResults"];
  
    if (!is_array($raw_results)) {
      throw new Exception("Error retrieving unit rest results");
    }

    //print_r($raw_results);
 
    $results = array();
    foreach ($raw_results as $result) 
    {
      if( isset($result["assertionResults"]) && count($result["assertionResults"])>0 )
      {
        //Get the filename
        $fileName = $result["name"];
        $startTime = $result["startTime"];
        $endTime = $result["endTime"];
        
        $elapsedSeconds = ($endTime - $startTime) / 1000;

        //Replace out the root path part for readability purposes
        $fileNameRel = str_replace($this->getRoot(),"",$fileName);

        foreach( $result["assertionResults"] as $assResult)
        {
          $test_result = new ArcanistUnitTestResult();
          $test_result->setName("[ ".$fileNameRel." ]\t".$assResult['title']);
          
          //So, this is dumb, BUT, timing is ONLY available on the test_results and not of each assertation.
          //So, what do we do? - we divide the elapsedSeconds by the total number of assertation results in order to get the avg time per assertation (test)
          $test_result->setDuration( $elapsedSeconds / count($result["assertionResults"]) );

          $succeed = isset($assResult['status']) && $assResult['status'] == 'passed';
          $test_result->setResult(
            $succeed ?
            ArcanistUnitTestResult::RESULT_PASS :
            ArcanistUnitTestResult::RESULT_FAIL
          );

        $messages = "";

        foreach($assResult['failureMessages'] as $message)
        {
          if( $messages )
            $messages.="\n\n";

          $messages.=$message;

        }
  
         $test_result->setUserData( $messages );
  
         $results[] = $test_result;
        }     
      }
      else
      {

        //Looks like the entire fail failed to run
        $test_result = new ArcanistUnitTestResult();
        $test_result->setName($result["name"]);
        $succeed = isset($assResult['status']) && $assResult['status'] == 'passed';
        $test_result->setResult(
          $succeed ?
          ArcanistUnitTestResult::RESULT_PASS :
          ArcanistUnitTestResult::RESULT_FAIL
        );
        
        $test_result->setUserData( $result["message"] );
  
        $results[] = $test_result;


      }    
    }
    
    return $results;

  } 
 
  private function getJestOptions($path) {

    $options = array(
      '--colors',
      '--json',
      '--watchAll=false',
      '--runInBand'
    );

    $options[] = '-i ';	//this should appear diretly before the list of test paths
 
    return $options;
  }
 
}
