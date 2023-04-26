<?php

namespace FriendsOfWp\VersionSecurityDevCliExtension\Command;

use FriendsOfWp\DeveloperCli\Util\ApiHelper;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class VersionSecurityCommand extends \FriendsOfWp\DeveloperCli\Command\Command
{
    const WORDPRESS_SECURITY_ENDPOINT = 'https://api.wordpress.org/core/stable-check/1.0/';

    protected static $defaultName = 'wordpress:security:version';
    protected static $defaultDescription = 'Check if a current WordPress version is insecure.';

    protected function configure()
    {
        $this->addArgument('version', InputArgument::REQUIRED, 'The version number you want to check.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->writeInfo($output, 'Check if a given WordPress version is secure.                  ');

        $versionsWithStatus = ApiHelper::JsonResponseRequest(self::WORDPRESS_SECURITY_ENDPOINT);

        $versionToCheck = $input->getArgument('version');

        if ($versionToCheck) {
            if (!array_key_exists($versionToCheck, $versionsWithStatus)) {
                $this->writeWarning($output, 'No version with name "' . $versionToCheck . '" found.');
                return SymfonyCommand::FAILURE;
            }
        }

        switch ($versionsWithStatus[$versionToCheck]) {
            case 'insecure':
                $nextSecureVersion = $this->getNextSecureVersion($versionsWithStatus, $versionToCheck);
                $messages[] = 'The version ' . $versionToCheck . ' was marked as insecure by the WordPress team.';
                $messages[] = 'You should upgrade immediately to ' . $nextSecureVersion . ' which is the closest';
                $messages[] = 'secure version to ' . $versionToCheck . '.';
                $this->writeWarning($output, $messages);
                break;
            case 'outdated':
                $this->writeInfo($output, 'The version ' . $versionToCheck . ' was marked as outdated by the WordPress team.');
                break;
            case 'latest':
                $this->writeInfo($output, 'The version ' . $versionToCheck . ' is the latest version.');
                break;
        }

        return SymfonyCommand::SUCCESS;
    }

    private function getNextSecureVersion(array $versionsWithStatus, string $versionToCheck)
    {
        $versionParts = explode('.', $versionToCheck);

        $minor = $versionParts[0] . '.' . $versionParts[1];
        $major = $versionParts[0];

        $minorVersions = [];
        $majorVersions = [];

        $latestVersion = '';

        foreach ($versionsWithStatus as $version => $status) {
            if ($status !== 'insecure' && str_starts_with($version, $minor)) {
                $minorVersions[] = $version;
            }

            if ($status !== 'insecure' && str_starts_with($version, $major) && $version > $versionToCheck) {
                $majorVersions[] = $version;
            }

            if ($status == 'latest') {
                $latestVersion = $version;
            }
        }

        if (count($minorVersions) > 0) {
            return $minorVersions[0];
        }

        if (count($majorVersions) > 0) {
            return $majorVersions[0];
        }

        return $latestVersion;
    }
}
