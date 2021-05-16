<?php


use Symfony\Component\HttpFoundation\Request;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use function source\getAbsolutePath;
use function source\getAbsolutePathSecondHost;

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
            new TwigFunction('getAbsolutePathSecondHost', [$this, 'getAbsolutePathSecondHost']),
            new TwigFunction('getWeekDayShortNameByDate', [$this, 'getWeekDayShortNameByDate']),
        ];
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

    /**
     * @param string $after
     * @param array $parts
     * @return string
     */
    public function getAbsolutePathSecondHost(array $parts = [], string $after = ""): string
    {
        return getAbsolutePathSecondHost($after, $parts);
    }
}