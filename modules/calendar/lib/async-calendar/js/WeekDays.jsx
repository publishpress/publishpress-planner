export default function WeekDays(props) {
    const DAYS_ON_A_WEEK = 7;
    const weekDayLabels = [
        props.strings.weekDaySun,
        props.strings.weekDayMon,
        props.strings.weekDayTue,
        props.strings.weekDayWed,
        props.strings.weekDayThu,
        props.strings.weekDayFri,
        props.strings.weekDaySat,
    ];

    let weekDays = [];
    let currentNumberOfDayOfWeek = (props.weekStartsOnSunday ? 0 : 1) - 1;

    for (let i = 0; i < DAYS_ON_A_WEEK; i++) {
        currentNumberOfDayOfWeek++;

        if (i === 6 && !props.weekStartsOnSunday) {
            currentNumberOfDayOfWeek = 0;
        }

        weekDays.push({
            key: currentNumberOfDayOfWeek.toString(),
            label: weekDayLabels[currentNumberOfDayOfWeek]
        });
    }

    return (
        <>
            {weekDays.map(item =>
                <th key={item.key.toString()} scope="col">{item.label}</th>
            )}
        </>
    )
}
