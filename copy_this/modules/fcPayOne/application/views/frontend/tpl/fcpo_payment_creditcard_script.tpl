<script>
    var request, config;
    config = {
        fields: {
            cardpan: {
                selector: "cardpan", // put name of your div-container here
                type: "tel", // text (default), password, tel
                size: 30,
                maxlength: 16
            },
            cardcvc2: {
                selector: "cardcvc", // put name of your div-container here
                type: "tel", // select(default), text, password, tel
                size: 30,
                maxlength: 4,
                length: { "A": 4, "V": 3, "M": 3, "J": 3, "D": 3, "O": 3, "C": 3, "B": 3 }
            },
            cardexpiremonth: {
                selector: "cardexpiremonth", // put name of your div-container here
                type: "select", // select(default), text, password, tel
                size: 5,
                maxlength: 4,

            },
            cardexpireyear: {
                selector: "cardexpireyear", // put name of your div-container here
                type: "select", // select(default), text, password, tel
                size: 5,
                maxlength: 4,
            }
        },
        defaultStyle: {
            input: "width:223px;height:30px;padding: 0 9px;font-size:14px;font-family:'Helvetica Neue',Verdana,Arial,sans-serif;",
            select: "width:100px",
            iframe: {
                width: "365px",
                height: "30px"
            }
        },
        error: "errorOutput", // area to display error-messages (optional)
    };


    [{capture name="fcpoCCIframes"}]
        [{foreach from=$oViewConf->fcpoGetIframeMappings() item='oMapping'}]

        [{/foreach}]
        var iframes = fcInitCCIframes(config);
    [{/capture}]
    [{oxscript add=$smarty.capture.fcpoCCIframes}]
</script>