<?php
namespace pocketmine\entity;

use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\Player;

class ExperienceOrb extends Entity{
	const NETWORK_ID = 69;

	public $width = 0.1;
	public $length = 0.1;
	public $height = 0.1;

	protected $gravity = 0.01;
	protected $drag = 0;
	
	public $experience = 0;

	public function initEntity(){
		parent::initEntity();
		if(isset($this->namedtag->Experience)){
			$this->experience = $this->namedtag["Experience"];
		}else $this->close();
	}
	
	public function FetchNearbyPlayer($DistanceRange){
		$MinDistance = $DistanceRange;
		$Target = null;
		foreach($this->getLevel()->getEntities() as $Entity){
			if($Entity instanceof Player){
				if($Entity->isAlive() and $MinDistance >= $Distance = $Entity->distance($this)){
					$Target = $Entity;
					$MinDistance = $Distance;
				}
			}
		}
		return $Target;
	}

	public function onUpdate($currentTick){
		if($this->closed){
			return false;
		}
		
		$tickDiff = $currentTick - $this->lastUpdate;
		
		$this->lastUpdate = $currentTick;
		
		$this->timings->startTiming();
		
		$hasUpdate = $this->entityBaseTick($tickDiff);
		
		$this->age++;
		if($this->age > 600){
			$this->timings->stopTiming();
			$this->close();
			return true;
		}
		
		if(!$this->onGround){
			$this->motionY -= $this->gravity;
		}
		
		$Target = $this->FetchNearbyPlayer(4);
		if ($Target !== null){
			$moveSpeed = 0.7;
			$motX = ($Target->getX() - $this->x) / 8;
			$motY = ($Target->getY() + $Target->getEyeHeight() - $this->y) / 8;
			$motZ = ($Target->getZ() - $this->z) / 8;
			$motSqrt = sqrt($motX * $motX + $motY * $motY + $motZ * $motZ);
			$motC = 1 - $motSqrt;
		
			if($motC > 0){
				$motC *= $motC;
				$this->motionX = $motX / $motSqrt * $motC * $moveSpeed;
				$this->motionY = $motY / $motSqrt * $motC * $moveSpeed;
				$this->motionZ = $motZ / $motSqrt * $motC * $moveSpeed;
			}
			
			if($Target->distance($this) <= 1.8){
				if($this->getExperience() > 0){
					$this->timings->stopTiming();
					$this->close();
					$Target->addExperience($this->getExperience());
					return true;
				}
			}
		}
		
		$this->move($this->motionX, $this->motionY, $this->motionZ);
		
		$this->updateMovement();
		
		$this->timings->stopTiming();

		return $hasUpdate or !$this->onGround or abs($this->motionX) > 0.00001 or abs($this->motionY) > 0.00001 or abs($this->motionZ) > 0.00001;
	}
	public function canCollideWith(Entity $entity){
		return false;
	}
	
	public function setExperience($exp){
		$this->experience = $exp;
	}
	
	public function getExperience(){
		return $this->experience;
	}

	public function spawnTo(Player $player){
		$this->setDataProperty(self::DATA_NO_AI, self::DATA_TYPE_BYTE, 1);
		$pk = new AddEntityPacket();
		$pk->type = ExperienceOrb::NETWORK_ID;
		$pk->eid = $this->getId();
		$pk->x = $this->x;
		$pk->y = $this->y;
		$pk->z = $this->z;
		$pk->speedX = $this->motionX;
		$pk->speedY = $this->motionY;
		$pk->speedZ = $this->motionZ;
		$pk->metadata = $this->dataProperties;
		$player->dataPacket($pk);

		parent::spawnTo($player);
	}
}