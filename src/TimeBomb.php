<?php

namespace Infonomicon\IrcBot;

use Phergie\Irc\Bot\React\AbstractPlugin;
use Phergie\Irc\Bot\React\EventQueueInterface as Queue;
use Phergie\Irc\Client\React\LoopAwareInterface;
use Phergie\Irc\Plugin\React\Command\CommandEventInterface as Event;
use React\EventLoop\LoopInterface;

/**
 * Time bomb plugin
 *
 * @author slick0
 */
class TimeBomb extends AbstractPlugin implements LoopAwareInterface
{
    /**
     * @var \React\EventLoop\LoopInterface
     */
    private $loop;

    /**
     * @var \React\EventLoop\Timer\TimerInterface
     */
    private $timer;

    /**
     * @var \Phergie\Irc\Plugin\React\Command\CommandEventInterface
     */
    private $ircEvent;

    /**
     * @var \Phergie\Irc\Bot\React\EventQueueInterface
     */
    private $ircQueue;

    /**
     * @var string
     */
    private $bombNick;

    /**
     * @var integer
     */
    private $wireCount;

    /**
     * @var integer
     */
    private $correctWireIndex;

    /**
     * @var array
     */
    private $wires = [
		'Red',
		'Orange',
		'Yellow',
		'Green',
		'Blue',
		'Indigo',
		'Violet',
		'Black',
		'White',
		'Grey',
		'Brown',
		'Pink',
		'Mauve',
		'Beige',
		'Aquamarine',
		'Chartreuse',
		'Bisque',
		'Crimson',
		'Fuchsia',
		'Gold',
		'Ivory',
		'Khaki',
		'Lavender',
		'Lime',
		'Magenta',
		'Maroon',
		'Navy',
		'Olive',
		'Plum',
		'Silver',
		'Tan',
		'Teal',
		'Turquoise',
    ];

    /**
     * {@inheritdoc}
     */
    public function getSubscribedEvents()
    {
        return [
            'command.timebomb' => 'startGame',
            'command.bombtoss' => 'handleToss',
            'command.cut' => 'handleCut',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function setLoop(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    /**
     * Check if a game is running
     *
     * @return bool
     */
    public function isRunning()
    {
        return null !== $this->timer;
    }

    /**
     * Start a game
     *
     * @param CommandEvent $event
     * @param Queue        $queue
     */
    public function startGame(Event $event, Queue $queue)
    {
        if ($this->isRunning()) {
            $this->sendAlreadyRunningMessage($event, $queue);
            return;
        }

        $params = $event->getCustomParams();

        $this->bombNick = isset($params[0]) ? $params[0] : $event->getNick();

        if (strtolower($this->bombNick) === strtolower($event->getConnection()->getNickname())) {
            $queue->ircKick($event->getSource(), $event->getNick(), "I will not tollerate this!");
            $this->endGame();
            return;
        }

        $this->ircEvent = $event;
        $this->ircQueue = $queue;

        $seconds = rand(90, 270);

        shuffle($this->wires);

        $this->wireCount = rand(1, 3);

        // If there's more than one wire, choose a correct one
        // If there's only one, give it a 50/50 chance
        if ($this->wireCount > 1) {
            $this->correctWireIndex = rand(0, $this->wireCount - 1);
        } else {
            $this->correctWireIndex = rand(0, 1);
        }

        $this->sendMessage("\x01ACTION stuffs the bomb into {$this->bombNick}'s pants.  The display reads [\x02$seconds\x02] seconds.\x01");

        if ($this->wireCount === 1) {
            $this->sendMessage("Diffuse the bomb by cutting the correct wire. There is {$this->getWireCountWord()} wire. It is {$this->listWires()}.  Use !cut <color>");
        } else {
            $this->sendMessage("Diffuse the bomb by cutting the correct wire. There are {$this->getWireCountWord()} wires. They are {$this->listWires()}.  Use !cut <color>");
        }

        $this->timer = $this->loop->addTimer($seconds, [$this, 'timerDetonate']);
    }

    /**
     * Get the word for a number (0-9)
     *
     * @return string
     */
    private function getWireCountWord()
    {
        $words = [
            0 => 'zero',
            1 => 'one',
            2 => 'two',
            3 => 'three',
            4 => 'four',
            5 => 'five',
            6 => 'six',
            7 => 'seven',
            8 => 'eight',
            9 => 'nine',
        ];

        return $words[$this->wireCount];
    }

    /**
     * Get the list of wires
     *
     * @return string
     */
    private function listWires()
    {
        $text = $this->wires[0];

        for ($i = 1; $i < $this->wireCount ; $i++) {
            if ($i === $this->wireCount - 1) {
                $text .= ' and ' . $this->wires[$i];
            } else {
                $text .= ', ' . $this->wires[$i];
            }
        }

        return $text;
    }

    /**
     * Callback when the time's up
     */
    public function timerDetonate()
    {
        $this->ircQueue->ircKick($this->ircEvent->getSource(), $this->bombNick, "\x02*BOOM!*\x02");
        $this->endGame();
    }

    /**
     * End the running game, resetting all properties
     */
    private function endGame()
    {
        $this->loop->cancelTimer($this->timer);
        $this->timer = null;
        $this->ircEvent = null;
        $this->ircQueue = null;
        $this->bombNick = null;
        $this->bombWires = null;
    }

    /**
     * Toss the bomb to another nick
     *
     * @param Event $event
     * @param Queue $queue
     */
    public function handleToss(Event $event, Queue $queue)
    {
        if (!$this->isRunning() || $event->getNick() !== $this->bombNick || $event->getSource() !== $this->ircEvent->getSource()) {
            return;
        }

        $params = $event->getCustomParams();

        if (count($params) < 1) {
            return;
        }

        if (strtolower($params[0]) === strtolower($this->bombNick)) {
            $this->sendMEssage("{$this->bombNick}... You're trying to toss the bomb to yourself!?");
            return;
        }

        $oldNick = $this->bombNick;
        $this->bombNick = $params[0];

        if (strtolower($this->bombNick) === strtolower($event->getConnection()->getNickname())) {
            $queue->ircKick($event->getSource(), $event->getNick(), "I will not tollerate this!");
            $this->endGame();
            return;
        }

        $this->sendMessage("{$oldNick} has tossed the bomb to \x02{$this->bombNick}\x02!");
    }

    /**
     * Check if a typed word is correct
     *
     * @param Event $event
     */
    public function handleCut(Event $event, Queue $queue)
    {
        if (!$this->isRunning() || $event->getNick() !== $this->bombNick || $event->getSource() !== $this->ircEvent->getSource()) {
            return;
        }

        $params = $event->getCustomParams();

        if (count($params) < 1) {
            return;
        }

        $wire = trim(strtolower($params[0]));

        for ($i = 0 ; $i < $this->wireCount ; $i++) {
            if ($wire === strtolower($this->wires[$i])) {
                if ($this->correctWireIndex === $i) {
                    $this->sendMessage("{$this->bombNick} cut the {$wire} wire.  This has defused the bomb!");
                } else {
                    $queue->ircKick($event->getSource(), $this->bombNick, "\x02snip...*BOOM!*\x02");
                }

                $this->endGame();
            }
        }
    }

    /**
     * Send a message when a user tries to play a
     * new game when one is already in progress
     *
     * @param Event $event
     * @param Queue $queue
     */
    private function sendAlreadyRunningMessage(Event $event, Queue $queue)
    {
        if ($event->getSource() === $this->ircEvent->getSource()) {
            $this->sendMessage("\x01ACTION points at the bulge in the back of {$this->bombNick}'s pants.\x01");
        } else {
            $queue->ircPrivmsg($event->getSource(), "I don't have a single bomb to spare. :-(");
        }
    }

    /**
     * Send a message to the source of the current game
     *
     * @param string $message
     */
    private function sendMessage($message)
    {
        $this->ircQueue->ircPrivmsg($this->ircEvent->getSource(), $message);
    }
}
