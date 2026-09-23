import NavigationBar from "./NavigationBar";
import WeekDays from "./WeekDays";
import MessageBar from "./MessageBar";
import DayCell from "./DayCell";
import {calculateWeeksInMilliseconds, getBeginDateOfWeekByDate, getDateAsStringInWpFormat, getDateInstanceFromString, addCalendarPosts, openPostModal, adjustTextareaHeight, updateModalPost, initToolTips} from "./Functions";
import FilterBar from "./FilterBar";
import ItemFormPopup from "./ItemFormPopup";

const $ = jQuery;

export default function AsyncCalendar(props) {
    const theme = (props.theme || 'light');

    let weeksValue    = (props.requestFilter.weeks) ? props.requestFilter.weeks : props.numberOfWeeksToDisplay;

    let calendarFiltersValue  = (props.requestFilter) ? props.requestFilter : {};

    const [firstDateToDisplay, setFirstDateToDisplay] = React.useState(props.firstDateToDisplay);
    const [numberOfWeeksToDisplay, setNumberOfWeeksToDisplay] = React.useState(weeksValue);
    const [itemsByDate, setItemsByDate] = React.useState(props.items);
    const [isLoading, setIsLoading] = React.useState(false);
    const [isDragging, setIsDragging] = React.useState(false);
    const [message, setMessage] = React.useState();
    const [filterWeeks, setFilterWeeks] = React.useState(weeksValue);
    const [searchText, setSearchText] = React.useState('');
    const [openedItemId, setOpenedItemId] = React.useState();
    const [openedItemData, setOpenedItemData] = React.useState([]);
    const [openedItemRefreshCount, setOpenedItemRefreshCount] = React.useState(0);
    const [refreshCount, setRefreshCount] = React.useState(0);
    const [hoveredDate, setHoveredDate] = React.useState();
    const [formDate, setFormDate] = React.useState();
    const [calendarFilter, setCalendarFilter] = React.useState(calendarFiltersValue);

    const DRAG_AND_DROP_HOVERING_CLASS = 'publishpress-calendar-day-hover';

    const getUrl = (action, query) => {
        if (!query) {
            query = '';
        }

        return props.ajaxUrl + '?action=' + action + '&nonce=' + props.nonce + query;
    }
    
    const onFilterSelectChange = (event) => {
        let selectElement = event.target;
        let selectName = selectElement.name;
        let selectValue = selectElement.value;
        let elementModal = selectElement.closest('.content-calendar-modal');
        let previousElement = elementModal.previousElementSibling;
        if (!selectValue || selectValue == '') {
            previousElement.classList.remove('active-filter');
            previousElement.innerHTML = previousElement.getAttribute('data-label');
        } else {
            let selectedOptionText = selectElement.selectedOptions[0].text;
            previousElement.classList.add('active-filter');
            previousElement.innerHTML = previousElement.getAttribute('data-label') + ': ' + selectedOptionText;
            elementModal.style.display = 'none';
        }
        onFilterEventCallback(selectName, selectValue);
    }

    const onModalNavClick = (event) => { 
        event.preventDefault();
        let openedItemId = event.currentTarget.getAttribute('data-post_id');

        openPostModal(openedItemId);
    };

    const onMeModeClick = (event) => { 
        const target = event.currentTarget;
        let new_value = '';
        if (target.classList.contains('active-filter')) {
            new_value = 0;
            target.classList.remove('active-filter');
        } else {
            new_value = 1;
            target.classList.add('active-filter');
        }

        onFilterEventCallback('me_mode', new_value);

        document.querySelector('#filter_author').value = '';
        document.querySelector('#pp-content-filters #content_calendar_me_mode').value = new_value;
    };

    const onShowRevisionClick = (event) => { 
        event.preventDefault();
        const target = event.currentTarget;
        let new_value = '';
        
        if (target.classList.contains('active-filter')) {
            new_value = 1;
            target.classList.remove('active-filter');
        } else {
            new_value = 0;
            target.classList.add('active-filter');
        }

        onFilterEventCallback('hide_revision', new_value);

        document.querySelector('#pp-content-filters #pp_hide_revision_input').value = new_value;
    };
    
    const onSearchClick = (event) => {
        let selectElement = event.target;
        let elementParent = selectElement.closest('.search-bar');
        let value = elementParent.querySelector('#co-searchbox-search-input').value;
        setSearchText(value);
    }

    const onFilterApplyClick = (event) => {
        event.preventDefault();
    
        let elementModal = event.target.closest('.content-calendar-modal');
        let previousElement = elementModal.previousElementSibling;
        let inputs = elementModal.querySelectorAll('input:not([type="submit"]), select, textarea');
        let all_inputs = [];
        let selected_values = '';
        inputs.forEach(input => {
            let inputName = input.name;
            let inputValue = input.value;

            if (input.type === 'checkbox') {
                inputValue = input.checked ? '1' : '';
                selected_values += inputValue;
            } else if (input.type === 'select-one') {
                selected_values += ' : ' + input.selectedOptions[0].text;
            } else if (input.type !== 'hidden' && inputValue && inputValue !== '') {
                selected_values += ' : ' + inputValue;
            }
            all_inputs.push(inputName);
    
            onFilterEventCallback(inputName, inputValue);
        });

        if (!selected_values || selected_values == '') {
            previousElement.classList.remove('active-filter');
            previousElement.innerHTML = previousElement.getAttribute('data-label');
        } else {
            previousElement.classList.add('active-filter');
            previousElement.innerHTML = previousElement.getAttribute('data-label') + selected_values;
        }
    
        elementModal.style.display = 'none';
    };
    

    const addEventListeners = () => {
        document.addEventListener('keydown', onDocumentKeyDown);
        $(document).on('publishpress_calendar:close_popup', onCloseItemPopup);
        $(document).on('change', '#pp-content-filters select:not(.non-trigger-select)', onFilterSelectChange);
        $(document).on('click', '.metadata-item-filter .filter-apply input[type=submit]', onFilterApplyClick);
        $(document).on('click', '.pp-content-calendar-manage .search-bar input[type=submit]', onSearchClick);
        $(document).on('click', '.pp-content-calendar-manage .me-mode-action', onMeModeClick);
        $(document).on('click', '.pp-content-calendar-manage .pp-show-revision-btn', onShowRevisionClick);
        $(document).on('click', '.pp-popup-modal-header .modal-nav-prev, .pp-popup-modal-header .modal-nav-next', onModalNavClick);
        $(document).on('input', '.pp-content-calendar-general-modal-container .modal-post-title .title-area', adjustTextareaHeight);
        $(document).on('click', '.pp-content-calendar-general-modal-container .modal-content-right .save-post-changes:not(.disabled)', function(e) {
            updateModalPost(e, jQuery(this), handleRefreshOnClick);
        });
    }

    const removeEventListeners = () => {
        document.removeEventListener('keydown', onDocumentKeyDown);
        $('.publishpress-calendar tbody > tr > td').off('mouseenter');
    }

    const didMount = () => {
        addEventListeners();

        if (props.userCanAddPosts) {
            initClickToCreateFeature();
        }

        return didUnmount;
    }

    const didUnmount = () => {
        removeEventListeners();
    }

    const fetchCalendarData = () => {
        setIsLoading(true);
        setMessage(props.strings.loading);

        let dataUrl = getUrl(props.actionGetData, '');

        if (calendarFilter && Object.keys(calendarFilter).length > 0) {
            if (calendarFilter.hasOwnProperty('start_date')) {
                delete calendarFilter.start_date;
            }
            if (calendarFilter.hasOwnProperty('number_of_weeks')) {
                delete calendarFilter.number_of_weeks;
            }
            let calendarFilterParams = new URLSearchParams(calendarFilter);
            dataUrl += '&' + calendarFilterParams.toString();
        }

        dataUrl += '&start_date=' + getDateAsStringInWpFormat(getBeginDateOfWeekByDate(firstDateToDisplay, props.weekStartsOnSunday)) + '&number_of_weeks=' + numberOfWeeksToDisplay;

        if (searchText) {
            dataUrl += '&s=' + searchText;
        } else {
            dataUrl += '&s=';
        }

        fetch(dataUrl)
            .then(response => response.json())
            .then((fetchedData) => {
                publishpressCalendarParams.PostData = addCalendarPosts(publishpressCalendarParams.PostData, fetchedData);
                setItemsByDate(fetchedData);
                setIsLoading(false);
                setMessage(null);

                resetCSSClasses();
                initToolTips();
            });
    };

    const resetCSSClasses = () => {
        $('.' + DRAG_AND_DROP_HOVERING_CLASS).removeClass(DRAG_AND_DROP_HOVERING_CLASS);
        $('.publishpress-calendar-loading').removeClass('publishpress-calendar-loading');
    };

    const fetchCalendarItemData = () => {
        if (!openedItemId) {
            return;
        }
        
        if (publishpressCalendarParams.proActive) {
            openPostModal(openedItemId);
        } else {
            setIsLoading(true);
            setMessage(props.strings.loadingItem);

            const dataUrl = props.ajaxUrl + '?action=' + 'publishpress_calendar_get_post_data' + '&nonce=' + props.nonce + '&id=' + openedItemId;
            fetch(dataUrl)
                .then(response => response.json())
                .then((data) => {
                    setIsLoading(false);
                    setMessage(null);

                    setOpenedItemData(data);
            });
        }
    }

    const addOffsetInWeeksToFirstDateToDisplay = (offsetInWeeks) => {
        setFirstDateToDisplay(new Date(firstDateToDisplay.getTime() + calculateWeeksInMilliseconds(offsetInWeeks)));
    };

    const handleRefreshOnClick = (e) => {
        e.preventDefault();

        setRefreshCount(prevCount => prevCount + 1);
    };

    const handleBackPageOnClick = (e) => {
        e.preventDefault();

        addOffsetInWeeksToFirstDateToDisplay(numberOfWeeksToDisplay * -1);
    };

    const handleBackOnClick = (e) => {
        e.preventDefault();

        addOffsetInWeeksToFirstDateToDisplay(-1);
    };

    const handleForwardOnClick = (e) => {
        e.preventDefault();

        addOffsetInWeeksToFirstDateToDisplay(1);
    }

    const handleForwardPageOnClick = (e) => {
        e.preventDefault();

        addOffsetInWeeksToFirstDateToDisplay(numberOfWeeksToDisplay);
    };

    const handleTodayOnClick = (e) => {
        e.preventDefault();

        setFirstDateToDisplay(getBeginDateOfWeekByDate(props.todayDate, props.weekStartsOnSunday));
    };

    const moveCalendarItemToANewDate = (itemDate, itemIndex, newYear, newMonth, newDay) => {
        if (!itemsByDate[itemDate]) {
            return;
        }
        let item = itemsByDate[itemDate][itemIndex];

        setIsLoading(true);
        setMessage(props.strings.movingTheItem);

        const dataUrl = getUrl(props.actionMoveItem);

        const formData = new FormData();
        formData.append('id', item.id);
        formData.append('year', newYear);
        formData.append('month', newMonth);
        formData.append('day', newDay);

        fetch(dataUrl, {method: 'POST', body: formData})
            .then(response => response.json())
            .then(() => {
                setRefreshCount(refreshCount + 1);
            });
    }

    const handleOnDropItemCallback = (event, ui) => {
        const $dayCell = $(event.target);
        const $item = $(ui.draggable[0]);
        const dateTime = getDateAsStringInWpFormat(getDateInstanceFromString($item.data('datetime')));

        $(event.target).addClass('publishpress-calendar-loading');

        moveCalendarItemToANewDate(
            dateTime,
            $item.data('index'),
            $dayCell.data('year'),
            $dayCell.data('month'),
            $dayCell.data('day')
        );
    };

    const handleOnHoverCellCallback = (event, ui) => {
        resetCSSClasses();

        $(event.target).addClass(DRAG_AND_DROP_HOVERING_CLASS);
    };

    const itemPopupIsOpenedById = (id) => {
        return id === openedItemId;
    }

    const initDraggableAndDroppableBehaviors = () => {
        $('.publishpress-calendar-day-items li').draggable({
            zIndex: 99999,
            helper: 'clone',
            containment: '.publishpress-calendar table',
            start: (event, ui) => {
                // Do not drag the item if the popup is opened.
                if (itemPopupIsOpenedById($(event.target).data('id'))) {
                    return false;
                }

                if (!$(event.target).hasClass('publishpress-calendar-item-movable')) {
                    return false;
                }

                $(event.target).addClass('ui-draggable-target');

                resetOpenedItemInPopup();

                setIsDragging(true);
            },
            stop: (event, ui) => {
                $('.ui-draggable-target').removeClass('ui-draggable-target');

                setIsDragging(false);
            }
        });

        $('.publishpress-calendar tbody > tr > td').droppable({
            drop: handleOnDropItemCallback,
            over: handleOnHoverCellCallback
        });
    };

    const getCellFromChild = (child) => {
        let $child = $(child);

        if ($child.is('td.publishpress-calendar-business-day, td.publishpress-calendar-weekend-day')) {
            return $child;
        }

        if ($child.is('.publishpress-calendar-cell-header, .publishpress-calendar-cell-button, .publishpress-calendar-date, .publishpress-calendar-cell-click-to-add, .publishpress-calendar-month-name')) {
            return $child.parents('td');
        }

        return null;
    }

    const getCellDate = (cell) => {
        let date = getDateInstanceFromString(cell.data('year') + '-' + cell.data('month') + '-' + cell.data('day'));

        // Compensate the timezone for returning the correct date
        if (date.getHours() > 0) {
            date.setTime(date.getTime() + (60 * 1000 * date.getTimezoneOffset()));
        }

        return date;
    }

    const isHoveringCellWhileDragging = (cell) => {
        return $(cell).hasClass(DRAG_AND_DROP_HOVERING_CLASS);
    }

    const initClickToCreateFeature = () => {
        // We have to use this variable because when the click is done on the "click to add" label, we can't get its parent.
        // Probably because it was already removed from the DOM.
        let lastHoveredDate;
        $('.publishpress-calendar tbody > tr > td')
            .on('mouseover', (e) => {
                e.preventDefault();
                e.stopPropagation();

                const cell = getCellFromChild(e.target);

                if (cell) {
                    if (isHoveringCellWhileDragging(cell)) {
                        return;
                    }

                    setHoveredDate(getCellDate(cell));
                    lastHoveredDate = getCellDate(cell);
                }
            })
            .on('mouseout', (e) => {
                e.stopPropagation();
                e.preventDefault();

                if (getCellFromChild(e.relatedTarget)) {
                    return;
                }

                setHoveredDate(null);
                lastHoveredDate = null;
            })
            .on('click', (e) => {
                const cell = getCellFromChild(e.target);

                if (cell) {
                    setOpenedItemId(null);
                    setFormDate(lastHoveredDate);
                }
            });
    }

    const onFilterEventCallback = (filterName, value) => {
        setCalendarFilter(prevCalendarFilter => ({
            ...prevCalendarFilter,
            [filterName]: value
        }));

        if ('weeks' === filterName) {
            value = parseInt(value);
            if (value === 0 || isNaN(value)) {
                value = props.numberOfWeeksToDisplay;
            }

            setFilterWeeks(value);
            setNumberOfWeeksToDisplay(value);

        }
    }

    const resetOpenedItemInPopup = () => {
        setOpenedItemId(null);
        setOpenedItemData(null);
        setFormDate(null);
    }

    const onClickItem = (id) => {
        setOpenedItemData(null);
        setHoveredDate(null);
        setFormDate(null);
        setOpenedItemId(id);
        
        if (publishpressCalendarParams.proActive) {
            openPostModal(id);
        }
    }

    const onPopupItemActionClick = (action, id, result) => {
        setOpenedItemRefreshCount(openedItemRefreshCount + 1);
    }

    const onDocumentKeyDown = (e) => {
        if (e.key === 'Escape') {
            resetOpenedItemInPopup();
        }
    }

    const getOpenedItemData = () => {
        return openedItemData;
    }

    const onCloseForm = () => {
        setRefreshCount(refreshCount + 1);

        setFormDate(null);
    }

    const onCloseItemPopup = () => {
        setOpenedItemId(null);
    }

    const onCreatePost = (date) => {
        setOpenedItemId(null);
        setFormDate(date);
    }

    const calendarTableBodyRowsWithCells = () => {
        const numberOfDaysToDisplay = numberOfWeeksToDisplay * 7;
        const firstDate = getBeginDateOfWeekByDate(firstDateToDisplay, props.weekStartsOnSunday);

        let tableRows = [];
        let rowCells = [];
        let dayIndexInTheRow = 0;
        let dayDate;
        let dateString;
        let lastMonthDisplayed = firstDate.getMonth();

        for (let dataIndex = 0; dataIndex < numberOfDaysToDisplay; dataIndex++) {
            if (dayIndexInTheRow === 0) {
                rowCells = [];
            }

            dayDate = new Date(firstDate);
            dayDate.setDate(dayDate.getDate() + dataIndex);
            dateString = getDateAsStringInWpFormat(dayDate);

            rowCells.push(
                <DayCell
                    key={'day-' + dayDate.getTime()}
                    date={dayDate}
                    shouldDisplayMonthName={lastMonthDisplayed !== dayDate.getMonth() || dataIndex === 0}
                    todayDate={props.todayDate}
                    isLoading={false}
                    isHovering={!isDragging && hoveredDate && hoveredDate.getTime() === dayDate.getTime() && !formDate}
                    items={itemsByDate[dateString] || []}
                    maxVisibleItems={parseInt(props.maxVisibleItems)}
                    timeFormat={props.timeFormat}
                    openedItemId={openedItemId}
                    getOpenedItemDataCallback={getOpenedItemData}
                    ajaxUrl={props.ajaxUrl}
                    onClickItemCallback={onClickItem}
                    onItemActionClickCallback={onPopupItemActionClick}
                    canCreate={props.userCanAddPosts}
                    onCreatePostCallback={onCreatePost}
                    strings={props.strings}/>
            );

            dayIndexInTheRow++;

            if (dayIndexInTheRow === 7) {
                dayIndexInTheRow = 0;
                tableRows.push(
                    <tr key={`calendar-row-${tableRows.length}`}>{rowCells}</tr>
                );
            }

            lastMonthDisplayed = dayDate.getMonth();
        }

        return tableRows;
    };

    React.useEffect(didMount, []);
    React.useEffect(initDraggableAndDroppableBehaviors);

    if (props.userCanAddPosts) {
        React.useEffect(
            initClickToCreateFeature,
            [
                firstDateToDisplay,
                numberOfWeeksToDisplay,
                filterWeeks,
                searchText,
                calendarFilter,
                refreshCount
            ]
        );
    }

    React.useEffect(
        fetchCalendarData,
        [
            firstDateToDisplay,
            numberOfWeeksToDisplay,
            filterWeeks,
            searchText,
            calendarFilter,
            refreshCount
        ]
    );
    React.useEffect(
        fetchCalendarItemData,
        [
            openedItemId,
            openedItemRefreshCount
        ]
    )

    let componentClassName = [
        'publishpress-calendar',
        'publishpress-calendar-theme-' + theme,
    ];

    if (props.userCanAddPosts) {
        componentClassName.push('user-can-add-posts');
    }

    return (
        <div className={componentClassName.join(' ')}>
            <NavigationBar
                refreshOnClickCallback={handleRefreshOnClick}
                backPageOnClickCallback={handleBackPageOnClick}
                backOnClickCallback={handleBackOnClick}
                forwardOnClickCallback={handleForwardOnClick}
                forwardPageOnClickCallback={handleForwardPageOnClick}
                todayOnClickCallback={handleTodayOnClick}
                strings={props.strings}/>

            <table>
                <thead>
                <tr>
                    <WeekDays weekStartsOnSunday={props.weekStartsOnSunday} strings={props.strings}/>
                </tr>
                </thead>
                <tbody>
                {calendarTableBodyRowsWithCells()}
                </tbody>
            </table>

            {formDate &&
            <ItemFormPopup
                date={formDate}
                ajaxUrl={props.ajaxUrl}
                dateFormat={props.dateFormat}
                postTypes={props.postTypesCanCreate}
                statuses={props.statuses}
                actionGetPostTypeFields={props.actionGetPostTypeFields}
                nonce={props.nonce}
                onCloseCallback={onCloseForm}
                allowAddingMultipleAuthors={props.allowAddingMultipleAuthors}
                strings={props.strings}/>
            }

            <MessageBar showSpinner={isLoading} message={message}/>
        </div>
    )
}
