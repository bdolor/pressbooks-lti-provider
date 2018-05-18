<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<manifest identifier="cctd0001"
          xmlns="http://www.imsglobal.org/xsd/imsccv1p3/imscp_v1p1"
          xmlns:lom="http://ltsc.ieee.org/xsd/imsccv1p3/LOM/resource"
          xmlns:lomimscc="http://ltsc.ieee.org/xsd/imsccv1p3/LOM/manifest"
          xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xsi:schemaLocation="http://ltsc.ieee.org/xsd/imsccv1p3/LOM/resource http://www.imsglobal.org/profile/cc/ccv1p3/LOM/ccv1p3_lomresource_v1p0.xsd
                  http://www.imsglobal.org/xsd/imsccv1p3/imscp_v1p1 http://www.imsglobal.org/profile/cc/ccv1p3/ccv1p3_imscp_v1p1_v1p0.xsd
                  http://ltsc.ieee.org/xsd/imsccv1p3/LOM/manifest http://www.imsglobal.org/profile/cc/ccv1p3/LOM/ccv1p3_lommanifest_v1p0.xsd">
    <metadata>
        <schema>IMS Common Cartridge</schema>
        <schemaversion>1.3.0</schemaversion>
        <lomimscc:lom>
            <lomimscc:general>
                <lomimscc:title>
                    <lomimscc:string language="{{ $lang }}">{{ $course_name }}</lomimscc:string>
                </lomimscc:title>
                <lomimscc:description>
                    <lomimscc:string language="{{ $lang }}">{{ $course_description }}</lomimscc:string>
                </lomimscc:description>
            </lomimscc:general>
        </lomimscc:lom>
    </metadata>
    <organizations>
        <organization identifier="O_1" structure="rooted-hierarchy">
            <item identifier="I_1">
                {!! $organization_items !!}
            </item>
        </organization>
    </organizations>
    <resources>
        {!! $resources !!}
    </resources>
</manifest>