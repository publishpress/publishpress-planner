import Button from './Button'

export default function NavigationBar(props) {
    return (
        <div className="publishpress-calendar-navigation-bar">
            <Button className="refresh-button" icon={'update-alt'} label={''} ariaLabel={props.strings.refresh} onClick={props.refreshOnClickCallback}/>
            <Button label={'«'} ariaLabel={props.strings.previousPage} onClick={props.backPageOnClickCallback}/>
            <Button label={'‹'} ariaLabel={props.strings.previousWeek} onClick={props.backOnClickCallback}/>
            <Button label={props.strings.today} onClick={props.todayOnClickCallback}/>
            <Button label={'›'} ariaLabel={props.strings.nextWeek} onClick={props.forwardOnClickCallback}/>
            <Button label={'»'} ariaLabel={props.strings.nextPage} onClick={props.forwardPageOnClickCallback}/>
        </div>
    )
}
