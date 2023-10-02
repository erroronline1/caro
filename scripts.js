var multiplecontainerID = 0

function getNextContainerID() {
    return "containerID" + ++multiplecontainerID;
}

const scroller = (e) => {
    /* event handler for horizontal scrolling of multiple panels */
    setTimeout(() => {
        let indicator = document.getElementById(e.target.attributes.id.value + "indicator");
        for (let panel = 0; panel < e.target.children.length; panel++) {
            if (panel == Math.floor(e.target.scrollLeft / e.target.clientWidth)) indicator.children[
                panel].firstChild.classList.add('sectionactive');
            else indicator.children[panel].firstChild.classList.remove('sectionactive');
        }
    }, 500)
};
