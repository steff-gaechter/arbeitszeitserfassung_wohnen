<?php


use Symfony\Component\HttpFoundation\Request;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use function source\getAbsolutePath;

class RIOCustomTwigExtension extends AbstractExtension
{
    private Request $request;

    public function __construct(Request $request) {
        $this->request = $request;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('getAbsolutePath', [$this, 'getAbsolutePath']),
            new TwigFunction('getSession', [$this, 'getSession']),
            new TwigFunction('getWeekDayShortNameByDate', [$this, 'getWeekDayShortNameByDate']),
            new TwigFunction('isLoggedIn', [$this, 'isLoggedIn']),
            new TwigFunction('logoLink', [$this, 'logoLink'])
        ];
    }

    /**
     * @param string $active
     * @return array
     */
    public function navByActive(string $active, $sessionUsername, $monthYear): array
    {
        return [
            "nav" => [
                [
                    "name" => "Zeiterfassung",
                    "active" => "user_home" === $active,
                    "link" => $this->getAbsolutePath(["main","sessionLogin"])
                ],
                [
                    "name" => "Benutzer",
                    "active" => "edit_user" === $active,
                    "link" => $this->getAbsolutePath(["admin","editUser", $sessionUsername])
                ],
                [
                    "name" => "Übersicht",
                    "active" => "overview" === $active,
                    "link" => $this->getAbsolutePath(["admin","overview", $sessionUsername, $monthYear])
                ]
            ]
        ];
    }

    public function logoLink(): string
    {
        if($this->isLoggedIn()) {
            return $this->getAbsolutePath(["sessionlogin"]);
        }
        return $this->getAbsolutePath();
    }

    public function isLoggedIn(): bool
    {
        $databaseCollection = new RIOMongoDatabaseCollection(RIOMongoDatabase::getInstance()->getDatabase(), "user");
        $collection = $databaseCollection->getCollection();
        $userFind = $collection->findOne(
            ["session_id" => $this->request->getSession()->getId()]
        );
        return null !== $userFind;
    }

    /**
     * @param string $date
     * @return string
     * @throws Exception
     */
    public function getWeekDayShortNameByDate(string $date): string
    {
        $givenDate = RIODateTimeFactory::getDateTime($date);
        $dayNames = [
            'Mo',
            'Di',
            'Mi',
            'Do',
            'Fr',
            'Sa',
            'So'
        ];
        return $dayNames[(int) $givenDate->format('N')-1];
    }

    public function getSession(string $session_key = null): ?string
    {
        return $this->request->getSession()->get($session_key);
    }

    /**
     * @param string $after
     * @param array $parts
     * @return string
     */
    public function getAbsolutePath(array $parts = [], string $after = ""): string
    {
        return getAbsolutePath($parts, $after);
    }
}