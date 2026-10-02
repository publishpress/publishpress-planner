import {
    calculateWeeksInMilliseconds,
    getBeginDateOfWeekByDate,
    getBeginDateOfWeekByWeekNumber,
    getDateAsStringInWpFormat,
    getDateWithNoTimezoneOffset,
    getHourStringOnFormat,
    getMonthNameByMonthIndex,
    getSelect2LanguageParameters,
    getWeekNumberByDate
} from "../async-calendar/js/Functions";

/*
 * getBeginDateOfWeekByWeekNumber, sunday as first day of week
 */
test('getBeginDateOfWeekByWeekNumber for week 13 in 2020 when sunday is the first day of week', () => {
    expect(
        getBeginDateOfWeekByWeekNumber(13, 2020, true)
    ).toStrictEqual(new Date(2020, 2, 22));
})

test('getBeginDateOfWeekByWeekNumber for week 33 in 2020 when sunday is the first day of week', () => {
    expect(
        getBeginDateOfWeekByWeekNumber(33, 2020, true)
    ).toStrictEqual(new Date(2020, 7, 9));
})

test('getBeginDateOfWeekByWeekNumber for week 18 in 2021 when sunday is the first day of week', () => {
    expect(
        getBeginDateOfWeekByWeekNumber(18, 2021, true)
    ).toStrictEqual(new Date(2021, 4, 2));
})

test('getBeginDateOfWeekByWeekNumber for week 51 in 2021 when sunday is the first day of week', () => {
    expect(
        getBeginDateOfWeekByWeekNumber(51, 2021, true)
    ).toStrictEqual(new Date(2021, 11, 19));
})

test('getBeginDateOfWeekByWeekNumber for week 10 in 2022 when sunday is the first day of week', () => {
    expect(
        getBeginDateOfWeekByWeekNumber(10, 2022, true)
    ).toStrictEqual(new Date(2022, 2, 6));
})

/*
 * getBeginDateOfWeekByWeekNumber, monday as first day of week
 */
test('getBeginDateOfWeekByWeekNumber for week 13 in 2020 when sunday is not the first day of week', () => {
    expect(
        getBeginDateOfWeekByWeekNumber(13, 2020, false)
    ).toStrictEqual(new Date(2020, 2, 23));
})

test('getBeginDateOfWeekByWeekNumber for week 8 in 2021 when sunday is not the first day of week', () => {
    expect(
        getBeginDateOfWeekByWeekNumber(8, 2021, false)
    ).toStrictEqual(new Date(2021, 1, 22));
})

test('getBeginDateOfWeekByWeekNumber for week 9 in 2021 when sunday is not the first day of week', () => {
    expect(
        getBeginDateOfWeekByWeekNumber(9, 2021, false)
    ).toStrictEqual(new Date(2021, 2, 1));
})

test('getBeginDateOfWeekByWeekNumber for week 18 in 2021 when sunday is not the first day of week', () => {
    expect(
        getBeginDateOfWeekByWeekNumber(18, 2021, false)
    ).toStrictEqual(new Date(2021, 4, 3));
})

test('getBeginDateOfWeekByWeekNumber for week 45 in 2021 when sunday is not the first day of week', () => {
    expect(
        getBeginDateOfWeekByWeekNumber(45, 2021, false)
    ).toStrictEqual(new Date(2021, 10, 8));
})

test('getBeginDateOfWeekByWeekNumber for week 10 in 2022 when sunday is not the first day of week', () => {
    expect(
        getBeginDateOfWeekByWeekNumber(10, 2022, false)
    ).toStrictEqual(new Date(2022, 2, 7));
})

/*
 * getWeekNumberByDate, sunday as first day of week
 */
test('getWeekNumberByDate for 2021-05-02 when sunday is the first day of week', () => {
    expect(
        getWeekNumberByDate(new Date(2021, 4, 2), true)
    ).toStrictEqual([2021, 18]);
})

test('getWeekNumberByDate for 2021-05-03 when sunday is the first day of week', () => {
    expect(
        getWeekNumberByDate(new Date(2021, 4, 3), true)
    ).toStrictEqual([2021, 18]);
})

test('getWeekNumberByDate for 2021-05-04 when sunday is the first day of week', () => {
    expect(
        getWeekNumberByDate(new Date(2021, 4, 4), true)
    ).toStrictEqual([2021, 18]);
})

test('getWeekNumberByDate for 2021-10-12 when sunday is the first day of week', () => {
    expect(
        getWeekNumberByDate(new Date(2021, 9, 12), true)
    ).toStrictEqual([2021, 41]);
})

test('getWeekNumberByDate for 2021-11-7 when sunday is the first day of week', () => {
    expect(
        getWeekNumberByDate(new Date(2021, 10, 7), true)
    ).toStrictEqual([2021, 45]);
})

/*
 * getWeekNumberByDate, monday as first day of week
 */
test('getWeekNumberByDate for 2021-05-02 when sunday is the first day of week', () => {
    expect(
        getWeekNumberByDate(new Date(2021, 4, 2), false)
    ).toStrictEqual([2021, 17]);
})

test('getWeekNumberByDate for 2021-05-03 when sunday is the first day of week', () => {
    expect(
        getWeekNumberByDate(new Date(2021, 4, 3), false)
    ).toStrictEqual([2021, 18]);
})

test('getWeekNumberByDate for 2021-10-12 when sunday is the first day of week', () => {
    expect(
        getWeekNumberByDate(new Date(2021, 9, 12), false)
    ).toStrictEqual([2021, 41]);
})

test('getWeekNumberByDate for 2021-11-7 when sunday is the first day of week', () => {
    expect(
        getWeekNumberByDate(new Date(2021, 10, 7), false)
    ).toStrictEqual([2021, 44]);
})

/*
 * getBeginDateOfWeekByAnyDate, sunday as first day of week
 */
test('getBeginDateOfWeekByAnyDate for 2021-5-12 when sunday is the first day of week', () => {
    expect(
        getBeginDateOfWeekByDate(new Date(2021, 4, 12), true)
    ).toStrictEqual(new Date(2021, 4, 9));
})

test('getBeginDateOfWeekByAnyDate for 2021-5-9 when sunday is the first day of week', () => {
    expect(
        getBeginDateOfWeekByDate(new Date(2021, 4, 9), true)
    ).toStrictEqual(new Date(2021, 4, 9));
})

test('getBeginDateOfWeekByAnyDate for 2021-10-11 when sunday is the first day of week', () => {
    expect(
        getBeginDateOfWeekByDate(new Date(2021, 9, 11), true)
    ).toStrictEqual(new Date(2021, 9, 10));
})

test('getBeginDateOfWeekByAnyDate for 2022-9-18 when sunday is the first day of week', () => {
    expect(
        getBeginDateOfWeekByDate(new Date(2022, 8, 18), true)
    ).toStrictEqual(new Date(2022, 8, 18));
})

test('getBeginDateOfWeekByAnyDate for 2021-5-4 when sunday is the first day of week', () => {
    expect(
        getBeginDateOfWeekByDate(new Date(2021, 4, 4), true)
    ).toStrictEqual(new Date(2021, 4, 2));
})

/*
 * getBeginDateOfWeekByAnyDate, monday as first day of week
 */
test('getBeginDateOfWeekByAnyDate for 2021-5-12 when monday is the first day of week', () => {
    expect(
        getBeginDateOfWeekByDate(new Date(2021, 4, 12), false)
    ).toStrictEqual(new Date(2021, 4, 10));
})

test('getBeginDateOfWeekByAnyDate for 2021-5-9 when monday is the first day of week', () => {
    expect(
        getBeginDateOfWeekByDate(new Date(2021, 4, 9), false)
    ).toStrictEqual(new Date(2021, 4, 3));
})

test('getBeginDateOfWeekByAnyDate for 2021-10-11 when monday is the first day of week', () => {
    expect(
        getBeginDateOfWeekByDate(new Date(2021, 9, 11), false)
    ).toStrictEqual(new Date(2021, 9, 11));
})

test('getBeginDateOfWeekByAnyDate for 2022-9-18 when monday is the first day of week', () => {
    expect(
        getBeginDateOfWeekByDate(new Date(2022, 8, 18), false)
    ).toStrictEqual(new Date(2022, 8, 12));
})

/*
 * getHourStringOnFormat, format ga
 */
test('getHourStringOnFormat, for time 2021-05-12 00:32:00 and format ga', () => {
    expect(
        getHourStringOnFormat(new Date(Date.parse('2021-05-12 00:32:00')), 'ga')
    ).toStrictEqual('12am');
})

test('getHourStringOnFormat, for time 2021-05-12 03:32:00 and format ga', () => {
    expect(
        getHourStringOnFormat(new Date(Date.parse('2021-05-12 03:32:00')), 'ga')
    ).toStrictEqual('3am');
})

test('getHourStringOnFormat, for time 2021-05-12 12:32:00 and format ga', () => {
    expect(
        getHourStringOnFormat(new Date(Date.parse('2021-05-12 12:32:00')), 'ga')
    ).toStrictEqual('12pm');
})

test('getHourStringOnFormat, for time 2021-05-12 14:32:00 and format ga', () => {
    expect(
        getHourStringOnFormat(new Date(Date.parse('2021-05-12 14:32:00')), 'ga')
    ).toStrictEqual('2pm');
})

/*
 * getHourStringOnFormat, format ha
 */
test('getHourStringOnFormat, for time 2021-05-12 00:32:00 and format ha', () => {
    expect(
        getHourStringOnFormat(new Date(Date.parse('2021-05-12 00:32:00')), 'ha')
    ).toStrictEqual('12am');
})

test('getHourStringOnFormat, for time 2021-05-12 04:32:00 and format ha', () => {
    expect(
        getHourStringOnFormat(new Date(Date.parse('2021-05-12 04:32:00')), 'ha')
    ).toStrictEqual('04am');
})

test('getHourStringOnFormat, for time 2021-05-12 15:32:00 and format ha', () => {
    expect(
        getHourStringOnFormat(new Date(Date.parse('2021-05-12 15:32:00')), 'ha')
    ).toStrictEqual('03pm');
})

/*
 * getHourStringOnFormat, format H
 */
test('getHourStringOnFormat, for time 2021-05-12 15:32:00 and format H', () => {
    expect(
        getHourStringOnFormat(new Date(Date.parse('2021-05-12 15:32:00')), 'H')
    ).toStrictEqual('15');
})

test('getHourStringOnFormat, for time 2021-05-12 00:32:00 and format H', () => {
    expect(
        getHourStringOnFormat(new Date(Date.parse('2021-05-12 00:32:00')), 'H')
    ).toStrictEqual('00');
})

test('getHourStringOnFormat, for time 2021-05-12 09:32:00 and format H', () => {
    expect(
        getHourStringOnFormat(new Date(Date.parse('2021-05-12 09:32:00')), 'H')
    ).toStrictEqual('09');
})

test('getHourStringOnFormat, for time 2021-05-12 23:32:00 and format H', () => {
    expect(
        getHourStringOnFormat(new Date(Date.parse('2021-05-12 23:32:00')), 'H')
    ).toStrictEqual('23');
})


/*
 * getDateAsStringInWpFormat
 */
test('getDateAsStringInWpFormat, for date 2021-05-12', () => {
    expect(
        getDateAsStringInWpFormat(new Date(Date.parse('2021-05-12 00:00:00')))
    ).toStrictEqual('2021-05-12');
})

test('getDateAsStringInWpFormat, for date 2022-06-05', () => {
    expect(
        getDateAsStringInWpFormat(new Date(Date.parse('2022-06-05 13:30:20')))
    ).toStrictEqual('2022-06-05');
})

/*
 * calculateWeeksInMilliseconds
 */
test('calculateWeeksInMilliseconds, for 1 week', () => {
    expect(
        calculateWeeksInMilliseconds(1)
    ).toStrictEqual(604800000);
})

test('calculateWeeksInMilliseconds, for 2 weeks', () => {
    expect(
        calculateWeeksInMilliseconds(2)
    ).toStrictEqual(1209600000);
})

test('calculateWeeksInMilliseconds, for 3 weeks', () => {
    expect(
        calculateWeeksInMilliseconds(3)
    ).toStrictEqual(1814400000);
})

/*
 * getMonthNameByMonthIndex
 */
test('getMonthNameByMonthIndex, for Jan', () => {
    expect(
        getMonthNameByMonthIndex(0)
    ).toStrictEqual('Jan');
})

test('getMonthNameByMonthIndex, for Feb', () => {
    expect(
        getMonthNameByMonthIndex(1)
    ).toStrictEqual('Feb');
})

test('getMonthNameByMonthIndex, for Mar', () => {
    expect(
        getMonthNameByMonthIndex(2)
    ).toStrictEqual('Mar');
})

test('getMonthNameByMonthIndex, for Apr', () => {
    expect(
        getMonthNameByMonthIndex(3)
    ).toStrictEqual('Apr');
})

test('getMonthNameByMonthIndex, for May', () => {
    expect(
        getMonthNameByMonthIndex(4)
    ).toStrictEqual('May');
})

test('getMonthNameByMonthIndex, for Jun', () => {
    expect(
        getMonthNameByMonthIndex(5)
    ).toStrictEqual('Jun');
})

test('getMonthNameByMonthIndex, for Jul', () => {
    expect(
        getMonthNameByMonthIndex(6)
    ).toStrictEqual('Jul');
})

test('getMonthNameByMonthIndex, for Aug', () => {
    expect(
        getMonthNameByMonthIndex(7)
    ).toStrictEqual('Aug');
})

test('getMonthNameByMonthIndex, for Sep', () => {
    expect(
        getMonthNameByMonthIndex(8)
    ).toStrictEqual('Sep');
})

test('getMonthNameByMonthIndex, for Oct', () => {
    expect(
        getMonthNameByMonthIndex(9)
    ).toStrictEqual('Oct');
})

test('getMonthNameByMonthIndex, for Nov', () => {
    expect(
        getMonthNameByMonthIndex(10)
    ).toStrictEqual('Nov');
})

test('getMonthNameByMonthIndex, for Dec', () => {
    expect(
        getMonthNameByMonthIndex(11)
    ).toStrictEqual('Dec');
})

/*
 * getDateWithNoTimezoneOffset, for 2021-06-03
 */
test('getDateWithNoTimezoneOffset', () => {
    const date = getDateWithNoTimezoneOffset('2021-06-03');

    expect(date.getFullYear()).toStrictEqual(2021);
    expect(date.getMonth()).toStrictEqual(5);
    expect(date.getDate()).toStrictEqual(3);
    expect(date.getHours()).toStrictEqual(0);
    expect(date.getMinutes()).toStrictEqual(0);
    expect(date.getSeconds()).toStrictEqual(0);
})

test('getDateInstanceFromString', () => {
    const date = getDateInstanceFromString('2021-06-03');

    expect(date.getFullYear()).toStrictEqual(2021);
    expect(date.getMonth()).toStrictEqual(5);
    expect(date.getDate()).toStrictEqual(3);
    expect(date.getHours()).toStrictEqual(0);
    expect(date.getMinutes()).toStrictEqual(0);
    expect(date.getSeconds()).toStrictEqual(0);
})

test('getSelect2LanguageParameters returns localized Select2 messages', () => {
    const previousParams = global.publishpressCalendarParams;

    global.publishpressCalendarParams = {
        strings: {
            noResults: 'Nessun risultato trovato',
            searching: 'Ricerca in corso…'
        }
    };

    try {
        const language = getSelect2LanguageParameters();

        expect(language.noResults()).toStrictEqual('Nessun risultato trovato');
        expect(language.searching()).toStrictEqual('Ricerca in corso…');
    } finally {
        if (previousParams === undefined) {
            delete global.publishpressCalendarParams;
        } else {
            global.publishpressCalendarParams = previousParams;
        }
    }
})
