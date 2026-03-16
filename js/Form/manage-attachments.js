export function initForm() {
  const attachmentList = cj("div.crm-form-block ul#activity-attachments");

  attachmentList.find(`li > a.delete-attachment`).click(({ target }) => {
    const fileId = target.getAttribute("data-file-id");
    const fileName = target.getAttribute("data-file-name");

    CRM.confirm({
        title: "Delete Activity attachment",
        message: `
          <p>Are you sure you want to delete the attached file <i>${fileName}</i> ?</p>
          <p>The file will be deleted immediately, this action cannot be undone!</p>
        `,
        options: { yes: "Yes", no: "No" },
    }).on("crmConfirm:yes", () => {
      CRM.api4("EntityFile", "delete", { where: [["file_id", "=", fileId]] }).then(
        () => CRM.api4("File", "delete", { where: [["id", "=", fileId]] }).then(
          () => attachmentList.find(`li[data-file-id=${fileId}]`).remove(),
          (error) => console.error(error),
        ),
        (error) => console.error(error),
      );
    });
  });
}
