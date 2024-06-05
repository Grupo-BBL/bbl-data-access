<?php


$functionsToCheck = [
    "stonewoodApp_idxHTMLFormatException",
    "stonewoodApp_idxErrorLogFormatException",
    "doOrCatchAndReport",
];


foreach ($functionsToCheck as $functionName)
{
    if (function_exists($functionName))
    {
        return;
    }
    else
    {
        error_log("Function does not exist: ".$functionName);
    }
}

function idx_containsKeywords($string, $keywords)
{
    foreach ($keywords as $keyword)
    {
        if (strpos($string, $keyword) !== false)
        {
            return true;
        }
    }

    return false;
}


function setPHPErrorLogPath()
{
    $repoRoot = dirname($_SERVER["DOCUMENT_ROOT"]);

    // Get today's date
    $date = date('Y-m-d');
    $pathParts = explode("/", $repoRoot);
    
    $nPathParts = count($pathParts);

    $releaseNumberIndex = $nPathParts - 1;
    $repoTypeIndex = $nPathParts - 2;

    $releaseNumber = $pathParts[$releaseNumberIndex];
    $repoType      = $pathParts[$repoTypeIndex];

    $errorLogName = $date.".".$repoType.".".$releaseNumber;

    $pathToLogAsArray = [];

    if (PHP_OS_FAMILY === 'Windows') 
    {
        $pathToLogAsArray[] = 'C:';
    }
    else
    {
        $pathToLogAsArray[] = '/var';
    }
    
    $pathToLogAsArray[] = "AppStonewood";
    $pathToLogAsArray[] = "Logs";
    $pathToLogAsArray[] = $repoType;
    $pathToLogAsArray[] = $errorLogName.".log";

    $errorLogPath = implode(DIRECTORY_SEPARATOR, $pathToLogAsArray);

    error_log("Setting error log path to: ".$errorLogPath);

    if (!file_exists($errorLogPath))
    {
        error_log("Creating error log file: ".$errorLogPath);
        file_put_contents($errorLogPath, "");
    }

    ini_set("error_log", $errorLogPath);
    
    return $errorLogPath;
}



function stonewoodApp_idxHTMLFormatException($exception, $detailed = false) 
{
    $html = "<div style='background: #f9f9f9; padding: 10px; border: 1px solid #ccc;'>";
    $html .= "<h2>Exception Details</h2>";
    $html .= "<table style='width: 100%; border-collapse: collapse;'>";
    $html .= "<tr><th style='background: #333; color: white; padding: 5px;'>Field</th><th style='background: #333; color: white; padding: 5px;'>Value</th></tr>";

    $html .= "<tr><td style='border: 1px solid #ddd; padding: 5px;'>Type</td><td style='border: 1px solid #ddd; padding: 5px;'>" . get_class($exception) . "</td></tr>";
    $html .= "<tr><td style='border: 1px solid #ddd; padding: 5px;'>Message</td><td style='border: 1px solid #ddd; padding: 5px;'>" . htmlspecialchars($exception->getMessage()) . "</td></tr>";
    $html .= "<tr><td style='border: 1px solid #ddd; padding: 5px;'>File</td><td style='border: 1px solid #ddd; padding: 5px;'>" . $exception->getFile() . "</td></tr>";
    $html .= "<tr><td style='border: 1px solid #ddd; padding: 5px;'>Line</td><td style='border: 1px solid #ddd; padding: 5px;'>" . $exception->getLine() . "</td></tr>";

    // Summarized Stack Trace
    $stackTrace = str_replace("\n", "<br>", htmlspecialchars($exception->getTraceAsString()));
    $html .= "<tr><td style='border: 1px solid #ddd; padding: 5px; vertical-align: top;'>Summarized Stack Trace</td><td style='border: 1px solid #ddd; padding: 5px;'>" . $stackTrace . "</td></tr>";
    $html .= "</table>";
    $html .= "</div>";


    
        
    if ($detailed)
    {
        try
        {
            $html .= "<h2>Detailed Stack Trace</h2>";
            $html .= "<table style='width: 100%; border-collapse: collapse;'>";
            // Detailed Stack Trace with Arguments
            $html .= "<tr><td style='border: 1px solid #ddd; padding: 5px; vertical-align: top;'>Detailed Stack Trace</td><td style='border: 1px solid #ddd; padding: 5px;'>";
            $trace = $exception->getTrace();
            foreach ($trace as $index => $traceLine) {
                $html .= "#{$index} ";
                if (isset($traceLine['file'])) {
                    $html .= htmlspecialchars($traceLine['file']) . '(' . htmlspecialchars($traceLine['line']) . '): ';
                }
                if (isset($traceLine['class'])) {
                    $html .= htmlspecialchars($traceLine['class']) . '->';
                }
                $html .= htmlspecialchars($traceLine['function']) . '(';

                if (isset($traceLine['args']) && !empty($traceLine['args'])) {
                    $html .= ')</td></tr>';
                    foreach ($traceLine['args'] as $argIndex => $arg) {
                        $argValue = '';
                        if (is_object($arg)) {
                            $argValue = get_class($arg);
                        } elseif (is_array($arg)) {
                            $argValue = 'Array';
                        } else {
                            $argValue = htmlspecialchars(var_export($arg, true));
                        }
                        $html .= "<tr><td style='border: 1px solid #ddd; padding: 5px; vertical-align: top;'>Argument #{$argIndex}</td><td style='border: 1px solid #ddd; padding: 5px;'>{$argValue}</td></tr>";
                    }
                    $html .= "<tr><td style='border: 1px solid #ddd; padding: 5px; vertical-align: top;'></td><td style='border: 1px solid #ddd; padding: 5px;'>";
                } else {
                    $html .= ')</td></tr>';
                }
            }
            $html .= "</table>";
        }
        catch (Exception $e)
        {
            error_log("Failed to get detailed stack trace: ".$e->getMessage()); 
        }
        catch (Error $e)
        {
            error_log("Failed to get detailed stack trace: ".$e->getMessage()); 
        }
    }

    // Route Accesed
    $html .= "<h2>Route Accessed</h2>";
    $html .= "<pre>";
    $html .= $_SERVER["REQUEST_URI"];
    $html .= "</pre>";

    // Current User
    $html .= "<h2>Current User Sessioncl</h2>";
    $html .= "<pre>";
    $html .= print_r($_COOKIE["AuthToken"] ?? "No user logged in", true);
    $html .= "</pre>";

    $html .= "<h3>GET</h3>";
    $html .= "<pre>";
    $html .= print_r($_GET, true);
    $html .= "</pre>";

    $html .= "<h3>POST</h3>";
    $html .= "<pre>";
    $html .= print_r($_POST, true);
    $html .= "</pre>";

    return $html;
}

/*
function stonewoodApp_idxErrorLogFormatException($exception) 
{
    $now = date('Y-m-d H:i:s');
    $formattedMessage = "\n\n\n Exception Occurred {$now}:" . PHP_EOL;
    $formattedMessage .= "{$now} Message: " . $exception->getMessage() . PHP_EOL;
    $formattedMessage .= "{$now} File: " . $exception->getFile() . PHP_EOL;
    $formattedMessage .= "{$now} Line: " . $exception->getLine() . PHP_EOL;
    $formattedMessage .= "{$now} Stack Trace:" . PHP_EOL;
    
    // Formatting each stack trace line to include a timestamp and ensure file names are on new lines
    $traceLines = explode("\n", $exception->getTraceAsString());
    foreach ($traceLines as $line) 
    {
        $section = explode("(", $line);
        $path = $section[0];
        $exceptionChainID = explode(" ", $path)[0];
        $fileName = basename($path);
        $rest = $section[1];
        $number = explode(":", $rest)[0];
        $method = explode(":", $rest)[1];
        $goTrad = false;
        
        if ($goTrad)
        {
            $formattedMessage .= "{$now} {$line}" . PHP_EOL;
        }
        else
        {
            $nowLen = strlen($now);

            $formattedMessage .= "{$now} {$exceptionChainID} {$fileName}:({$number})\n".str_repeat("-", $nowLen+8)." {$method}" . PHP_EOL;
        }
    }



    return $formattedMessage;
}
*/

function stonewoodApp_idxErrorLogFormatException($exception) 
{
    $now = date('Y-m-d H:i:s');
    $formattedMessage = "\n\n\n Exception Occurred {$now}:" . PHP_EOL;
    $formattedMessage .= "{$now} Message: " . $exception->getMessage() . PHP_EOL;
    $formattedMessage .= "{$now} File: " . $exception->getFile() . PHP_EOL;
    $formattedMessage .= "{$now} Line: " . $exception->getLine() . PHP_EOL;
    $formattedMessage .= "{$now} Stack Trace:" . PHP_EOL;

    // Get the trace
    $trace = $exception->getTrace();
    
    // Format each stack trace line
    foreach ($trace as $index => $traceLine) 
    {
        $formattedMessage .= "{$now} #{$index} ";
        if (isset($traceLine['file'])) {
            $formattedMessage .= $traceLine['file'] . '(' . $traceLine['line'] . '): ';
        }
        if (isset($traceLine['class'])) {
            $formattedMessage .= $traceLine['class'] . '->';
        }
        $formattedMessage .= $traceLine['function'] . '(';
        
        if (isset($traceLine['args']) && !empty($traceLine['args'])) {
            $args = [];
            foreach ($traceLine['args'] as $arg) {
                if (is_object($arg)) {
                    $args[] = get_class($arg);
                } elseif (is_array($arg)) {
                    $args[] = 'Array';
                } else {
                    $args[] = var_export($arg, true);
                }
            }
            $formattedMessage .= implode(', ', $args);
        }
        
        $formattedMessage .= ')' . PHP_EOL;
    }

    return $formattedMessage;
}



function doOrCatchAndReport($function)
{
    $debug = false;

    $containsLocal = idx_containsKeywords($_SERVER["HTTP_HOST"], [
        "local",
    ]);
    
    $errorLogPath = ini_get("error_log");
    
    if (!$containsLocal)
    {
        error_log("Running `debug.php` - error log original path: ".$errorLogPath);
        $errorLogPath = setPHPErrorLogPath();
    }
    
    $shouldPrintToScreen = idx_containsKeywords($_SERVER["HTTP_HOST"], [
        "local",
        "prueba",
    ]);
    
    
    if (str_ends_with($_SERVER["REQUEST_URI"], ".js"))
    {
        die("`router` --- Requesting unauthorized JavaScript.");
    }
    
    
    if ($shouldPrintToScreen || $debug)
    {
        if ($debug)
        {
            echo "<h1>Debug Mode</h1>";
        }
        echo "Error Log Path: ".$errorLogPath;
        echo "<br/>";
        echo "Request URI: ".$_SERVER["REQUEST_URI"];
        echo "<br>";
        echo "HTTP HOST: ".$_SERVER["HTTP_HOST"];
        echo "<br>";
        echo "Contains Local? ".($containsLocal ? "Yes" : "No")."<br>";
    }
    

    $guid = uniqid();

    $toPrintOnScreen = "";
    $toPrintOnScreen = "<h1>";
    $toPrintOnScreen .= "Ha occurido un error en el sistema.";
    $toPrintOnScreen .= "El equipo de tecnologia ha sido notificado.";
    $toPrintOnScreen .= "Favor darle este numero: ".$guid;
    $toPrintOnScreen .= "</h1>";

    $errorEmail = "gtavares@stonewood.com.do";

    try
    {
        $function();
    }
    catch (Exception $e)
    {
        error_log("=================================== $guid ===================================");
        error_log(stonewoodApp_idxErrorLogFormatException($e));

        try
        {
            DataAccessManager::get("email_queue")->addToQueue(
                $errorEmail,
                "STD Ex: ".$guid." - ".$e->getMessage(),
                stonewoodApp_idxHTMLFormatException($e)."\n\n\n".stonewoodApp_idxErrorLogFormatException($e),
            );
    
            error_log("Added to queue - email with error message to `$errorEmail` - ".$e->getMessage());
        }
        catch (Exception $e)
        {
            error_log("XXXXXXXXXXX --- Failed to send email");
        }
        catch (Error $e)
        {
            error_log("XXXXXXXXXXX --- Failed to send email");
        }
    
        if ($containsLocal)
        {
            echo stonewoodApp_idxHTMLFormatException($e);
        }
        else
        {
            die($toPrintOnScreen);
        }
    }
    catch (Error $e)
    {
        
        error_log("=================================== $guid ===================================");
        error_log(stonewoodApp_idxErrorLogFormatException($e));
          // This will catch any Error, including TypeError

        try
        {
            DataAccessManager::get("email_queue")->addToQueue(
                $errorEmail,
                "STD Ex: ".$guid." - ".$e->getMessage(),
                stonewoodApp_idxHTMLFormatException($e)."\n\n\n".stonewoodApp_idxErrorLogFormatException($e),
            );
    
            error_log("Added to queue - email with error message to `$errorEmail` - ".$e->getMessage());
        }
        catch (Exception $e)
        {
            error_log("XXXXXXXXXXX --- Failed to send email");
        }
        catch (Error $e)
        {
            error_log("XXXXXXXXXXX --- Failed to send email");
        }

        if ($containsLocal) 
        {
            // Assuming you want to handle Error instances similarly to Exception instances
            // You might need to adjust the function if it's strictly typed to accept only Exception instances
            echo stonewoodApp_idxHTMLFormatException($e);
        } else {
            error_log("Error: ".$e->getMessage());
            die($toPrintOnScreen);        }
    }
}
