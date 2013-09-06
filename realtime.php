<?php

require __DIR__ . '/libs/minecraftRcon.class.php';

class RealTimeMinecraft
{
	private $time;
	private $lastTime;
	private $minecraftTime;
	private $lastMinecraftTime;
	
	private $rcon; // The RCon instance
	
	public $config; // Configuration of the deamon
	
	private $run = TRUE; // You want to stop the infinit loop ?
	
	const REAL_DAY = 86400; // Number of seconds in one real day
	const MINECRAFT_DAY = 24000; // Number of ticks in one minecraft day
	const DELAY = 21600; // Number of seconds of delay (Because 0 ticks isn't 00h00 in a real day)
	
	// Construct function
	public function __construct($cmdConfig = array())
	{
		$fileConfig = parse_ini_file('realtime.ini');
		
		// Initialisation
		$this->configInit($cmdConfig, $fileConfig);

		// Rcon connection
		$this->connect();
	}
	
	// Initialisation function
	private function configInit($cmdConfig, $fileConfig)
	{
		// Variables init
		$this->time = 0;
		$this->lastTime = 0;
		$this->minecraftTime = 0;
		$this->lastMinecraftTime = 0;
		
		$this->config = array_merge($fileConfig, $cmdConfig);
		$this->config['timeout'] = intval($this->config['timeout']);
	
		return TRUE;
	}
	
	// RCon connection function
	private function connect()
	{
		$this->rcon = new minecraftRcon();
		
		try
		{
			$this->rcon->connect($this->config['hostname'], $this->config['port'], $this->config['password'], intval($this->config['timeout']));
			$result = $this->rcon->command('gamerule doDaylightCycle false');
			
			if($result == "No game rule called 'doDaylightCycle' is available")
			{
				// TODO : make this better with an Exception or Logs
				echo "Minecraft server version must be >= 1.6\n";
				die();
			}
	
			return TRUE;
		}
		catch( Exception $e )
		{
			echo $e->getMessage( );
	
			return FALSE;
		}
	}
	
	// Send to the server
	private function send($cmd)
	{
		$notsended = TRUE;
		
		while($notsended)
		{
			// send the rcon
			if($this->rcon->command($cmd))
			{
				$notsended = FALSE;
			}
			else // Try to reconnect
			{
				echo "Reconection...";
				$this->rcon->disconnect();
				try
				{
					$this->rcon->connect($this->config['hostname'], $this->config['port'], $this->config['password'], $this->config['timeout']);
				}
				catch( Exception $e )
				{
					echo $e->getMessage( );
				}
			}
			
			// Wait a second
			sleep(10);
		}
	}
	
	// unix signals
	public function signals($signal)
	{
		// TODO : mke this function
		// kill -> $this->run = false;
		// reload -> $this->init();
	}
	
	// Gooooo !
	public function step()
	{
		// Real time day between 0 and 86400
		$daytime = mktime(0, 0, 0, date('n'), date('j'), date('Y'));
		$this->time = time()-$daytime;
		
		// Minecraft time calculations
		$this->minecraftTime = intval(($this->time-self::DELAY)*self::MINECRAFT_DAY/self::REAL_DAY);
		if($this->minecraftTime < 0) $this->minecraftTime = MINECRAFT_DAY+$this->minecraftTime;
		
		// If y need to change the time on minecraft
		if($this->time != $this->lastTime && $this->minecraftTime != $this->lastMinecraftTime)
		{
			// Send RCon with $minecraftTime
			$this->send('time set '.$this->minecraftTime);
			//echo "Send : ".$this->minecraftTime."\n";
			
			// Update "last" variables
			$this->lastTime = $this->time;
			$this->lastMinecraftTime = $this->minecraftTime;
		}
	}
	
	// Gooooo !
	public function run()
	{
		// Infinity loop
		while($this->run)
		{
			// make a step forward
			$this->step();
			
			// Wait a second
			sleep(1);
		}
	}
}

$instance = new RealTimeMinecraft();
$instance->run();
?>