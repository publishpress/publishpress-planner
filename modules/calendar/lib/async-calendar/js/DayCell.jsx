import {getMonthNameByMonthIndex} from "./Functions";
import Item from "./Item";

export default function DayCell(props) {
    const [uncollapseItems, setUncollapseItems] = React.useState(false);

    let itemIndex = 0;

    const getDayItemClassName = (dayDate, todayDate) => {
        const businessDays = [1, 2, 3, 4, 5];

        let dayItemClassName = businessDays.indexOf(dayDate.getDay()) >= 0 ? 'business-day' : 'weekend-day'

        if (todayDate.getFullYear() === dayDate.getFullYear()
            && todayDate.getMonth() === dayDate.getMonth()
            && todayDate.getDate() === dayDate.getDate()
        ) {
            dayItemClassName += ' publishpress-calendar-today';
        }

        if (props.isLoading) {
            dayItemClassName += ' publishpress-calendar-loading';
        }

        if (uncollapseItems) {
            dayItemClassName += ' publishpress-calendar-uncollapse';
        }

        if (dayDate.getDate() === 1) {
            dayItemClassName += ' publishpress-calendar-first-day-of-month';
        }

        if (props.isHovering) {
            dayItemClassName += ' publishpress-calendar-hovering';
        }

        return 'publishpress-calendar-' + dayItemClassName;
    };

    const toggleUncollapseItems = () => {
        setUncollapseItems(!uncollapseItems);
    };

    const createPost = (event) => {
        event.preventDefault();
        event.stopPropagation();
        props.onCreatePostCallback(props.date);
    };

    const dayLabel = props.strings.addContentFor.replace(
        '%s',
        getMonthNameByMonthIndex(props.date.getMonth()) + ' ' + props.date.getDate() + ', ' + props.date.getFullYear()
    );

    const uncollapseButton = () => {
        if (props.maxVisibleItems === -1) {
            return (<></>);
        }

        if (props.items.length > props.maxVisibleItems) {
            const numberOfExtraItems = props.items.length - props.maxVisibleItems;
            const hideItems = props.strings.hideItems;
            const showMore = props.strings.showMore;

            const label = uncollapseItems ? hideItems.replace('%s', numberOfExtraItems) : showMore.replace('%s', numberOfExtraItems);
            const className = uncollapseItems ? 'publishpress-calendar-hide-items' : 'publishpress-calendar-show-more';
            const iconClass = uncollapseItems ? 'hidden' : 'visibility';

            return (
                <button type="button"
                    className={className}
                    onClick={toggleUncollapseItems}><span className={'dashicons dashicons-' + iconClass}/> {label}</button>
            );
        }

        return (<></>);
    }

    const visibleItems = uncollapseItems || props.maxVisibleItems === -1 ? props.items : props.items.slice(0, props.maxVisibleItems);

    return (
        <td
            className={getDayItemClassName(props.date, props.todayDate)}
            data-year={props.date.getFullYear()}
            data-month={props.date.getMonth() + 1}
            data-day={props.date.getDate()}>
            <div>
                <div className="publishpress-calendar-cell-header">
                    {props.canCreate ?
                    <button type="button" className="publishpress-calendar-cell-button" onClick={createPost} aria-label={dayLabel}>
                        {props.shouldDisplayMonthName &&
                        <span className="publishpress-calendar-month-name">{getMonthNameByMonthIndex(props.date.getMonth())}</span>
                        }
                        <span className="publishpress-calendar-date">{props.date.getDate()}</span>
                        {props.isHovering &&
                        <span className="publishpress-calendar-cell-click-to-add">{props.strings.clickToAdd}</span>
                        }
                    </button>
                    :
                    <>
                        {props.shouldDisplayMonthName &&
                        <span className="publishpress-calendar-month-name">{getMonthNameByMonthIndex(props.date.getMonth())}</span>
                        }
                        <span className="publishpress-calendar-date">{props.date.getDate()}</span>
                    </>
                    }
                </div>

                <ul className="publishpress-calendar-day-items">
                    {visibleItems.map(item => {
                        const isPopupOpened = item.id === props.openedItemId;

                        return (
                            <Item
                                key={'item-' + item.id + '-' + props.date.getTime()}
                                icon={item.icon}
                                color={item.color}
                                label={item.label}
                                id={item.id}
                                timestamp={item.timestamp}
                                timeFormat={props.timeFormat}
                                showTime={item.showTime}
                                showIcon={true}
                                index={itemIndex++}
                                canMove={item.canEdit}
                                isPopupOpened={isPopupOpened}
                                getPopupItemDataCallback={props.getOpenedItemDataCallback}
                                onClickItemCallback={props.onClickItemCallback}
                                onItemActionClickCallback={props.onItemActionClickCallback}
                                ajaxUrl={props.ajaxUrl}
                                strings={props.strings}
                            />
                        )
                    })}
                </ul>

                {uncollapseButton()}
            </div>
        </td>
    )
}
