<?php
namespace App\Telegram\Commands;    

trait StartCommand
{
    public function start(): void
    {
        $this->initializeServices();
        $this->startService->handleStart();
    }

    public function info(): void
    {
        $this->initializeServices();
        $this->infoService->handleInfo();
    }

    public function about(): void
    {
        $this->initializeServices();
        $this->infoService->handleAbout();
    }

    public function contact(): void
    {
        $this->initializeServices();
        $this->infoService->handleContact();
    }

    public function help(): void
    {
        $this->initializeServices();
        $this->helpService->handleHelp();
    }

    public function onContactReceived(array $contact): void
    {
        $this->initializeServices();
        $this->startService->handleContactReceived($contact);
    }
}